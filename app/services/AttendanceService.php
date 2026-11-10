<?php
/**
 * Raptor CRM — Attendance Business Logic Service
 * -------------------------------------------------------------------------
 * Decouples attendance evaluation, geofence verification, shift grace periods,
 * break durations, and daily clock-in rules from legacy controllers and models.
 */

declare(strict_types=1);

class AttendanceService {

    /**
     * Compute Great-Circle distance between two coordinates in metres using Haversine formula.
     */
    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadius = 6371000.0; // metres
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) ** 2;
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    /**
     * Evaluate whether a coordinate is inside any active office geofence.
     * Returns:
     *   null: Geofencing is disabled or no fences configured
     *   1:    Coordinate is inside at least one active geofence
     *   0:    Coordinate is outside all active geofences
     */
    public static function evaluateGeofence(?float $lat, ?float $lng, array $geofences = [], bool $geofenceEnabled = true): ?int {
        if (!$geofenceEnabled) {
            return null;
        }

        if ($lat === null || $lng === null) {
            return 0;
        }

        if (empty($geofences)) {
            return null;
        }

        foreach ($geofences as $fence) {
            if (is_object($fence)) {
                $fLat = (float)($fence->center_lat ?? 0);
                $fLng = (float)($fence->center_lng ?? 0);
                $radius = (float)($fence->radius_m ?? 100);
            } else {
                $fLat = (float)($fence['center_lat'] ?? 0);
                $fLng = (float)($fence['center_lng'] ?? 0);
                $radius = (float)($fence['radius_m'] ?? 100);
            }

            $dist = self::haversineDistance($lat, $lng, $fLat, $fLng);
            if ($dist <= $radius) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Parse and normalize shift configuration settings.
     */
    public static function normalizeShiftConfig(array $rawConfig): array {
        return [
            'shift_start'     => (string)($rawConfig['shift_start'] ?? '09:30'),
            'shift_end'       => (string)($rawConfig['shift_end'] ?? '18:30'),
            'grace_minutes'   => (int)($rawConfig['grace_minutes'] ?? 15),
            'halfday_minutes' => (int)($rawConfig['halfday_minutes'] ?? 240),
            'policy_version'  => (string)($rawConfig['policy_version'] ?? 'v1'),
            'geofence_enabled'=> !empty($rawConfig['geofence_enabled']),
        ];
    }

    /**
     * Evaluate shift start time, grace period, and determine if check-in is late.
     */
    public static function evaluateLateness(string $checkInTime, array $shiftConfig): array {
        $checkInTs = strtotime($checkInTime);
        if ($checkInTs === false) {
            return ['is_late' => false, 'late_minutes' => 0, 'grace_exceeded' => false];
        }

        $workDate = date('Y-m-d', $checkInTs);
        $shiftStartStr = $shiftConfig['shift_start'] ?? '09:30';
        $graceMinutes = (int)($shiftConfig['grace_minutes'] ?? 15);

        $shiftStartTs = strtotime("{$workDate} {$shiftStartStr}:00");
        $graceCutoffTs = $shiftStartTs + ($graceMinutes * 60);

        if ($checkInTs > $graceCutoffTs) {
            $lateMinutes = (int)ceil(($checkInTs - $shiftStartTs) / 60);
            return [
                'is_late' => true,
                'late_minutes' => $lateMinutes,
                'grace_exceeded' => true,
                'grace_minutes' => $graceMinutes
            ];
        }

        return [
            'is_late' => false,
            'late_minutes' => 0,
            'grace_exceeded' => false,
            'grace_minutes' => $graceMinutes
        ];
    }

    /**
     * Calculate net work minutes excluding completed break periods.
     */
    public static function calculateWorkDuration(string $checkInTime, string $checkOutTime, array $breaks = []): array {
        $inTs = strtotime($checkInTime);
        $outTs = strtotime($checkOutTime);

        if ($inTs === false || $outTs === false || $outTs < $inTs) {
            return [
                'gross_minutes' => 0,
                'break_minutes' => 0,
                'net_minutes' => 0,
                'status' => 'absent'
            ];
        }

        $grossMinutes = (int)round(($outTs - $inTs) / 60);
        $breakMinutes = 0;

        foreach ($breaks as $b) {
            if (is_object($b)) {
                $bStart = strtotime((string)($b->break_start ?? ''));
                $bEnd = strtotime((string)($b->break_end ?? ''));
            } else {
                $bStart = strtotime((string)($b['break_start'] ?? ''));
                $bEnd = strtotime((string)($b['break_end'] ?? ''));
            }
            if ($bStart && $bEnd && $bEnd > $bStart) {
                $breakMinutes += (int)round(($bEnd - $bStart) / 60);
            }
        }

        $netMinutes = max(0, $grossMinutes - $breakMinutes);
        $status = ($netMinutes >= 480) ? 'present' : (($netMinutes >= 240) ? 'half_day' : 'absent');

        return [
            'gross_minutes' => $grossMinutes,
            'break_minutes' => $breakMinutes,
            'net_minutes'   => $netMinutes,
            'status'        => $status
        ];
    }

    /**
     * Validate whether an active break can be initiated or ended.
     */
    public static function validateBreakAction(string $action, ?object $openBreak, int $totalTodayBreakMinutes = 0, int $maxAllowedMinutes = 60): array {
        if ($action === 'start') {
            if ($openBreak !== null) {
                return ['allowed' => false, 'message' => 'You already have an active break in progress.'];
            }
            if ($totalTodayBreakMinutes >= $maxAllowedMinutes) {
                return ['allowed' => true, 'warning' => 'Exceeding standard break allowance threshold (' . $maxAllowedMinutes . ' mins).'];
            }
            return ['allowed' => true, 'message' => 'Break started successfully.'];
        }

        if ($action === 'end') {
            if ($openBreak === null) {
                return ['allowed' => false, 'message' => 'No active break was found to end.'];
            }
            return ['allowed' => true, 'message' => 'Break ended successfully.'];
        }

        return ['allowed' => false, 'message' => 'Invalid break action.'];
    }
}
