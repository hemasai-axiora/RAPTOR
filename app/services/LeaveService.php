<?php
/**
 * Raptor CRM — Leave Management & Balance Service
 * -------------------------------------------------------------------------
 * Decouples leave balance arithmetic, statutory entitlement verification,
 * weekend exclusions, and holiday calendar arithmetic from controllers.
 */

declare(strict_types=1);

class LeaveService {

    /**
     * Standard statutory and company observed holidays by year.
     */
    public static function getStatutoryHolidays(int $year): array {
        // Formatted statutory holiday dates for the year
        return [
            "{$year}-01-01" => 'New Year\'s Day',
            "{$year}-01-26" => 'Republic Day',
            "{$year}-03-14" => 'Holi',
            "{$year}-05-01" => 'International Labour Day',
            "{$year}-08-15" => 'Independence Day',
            "{$year}-10-02" => 'Mahatma Gandhi Jayanti',
            "{$year}-10-20" => 'Dussehra / Vijayadashami',
            "{$year}-11-08" => 'Diwali / Deepavali',
            "{$year}-12-25" => 'Christmas Day',
        ];
    }

    /**
     * Calculate net working business days between two dates,
     * automatically excluding Saturdays, Sundays, and declared holidays.
     * 
     * @param string $fromDate Y-m-d format
     * @param string $toDate   Y-m-d format
     * @param bool   $isHalfDay If true, counts as 0.5 day
     * @param array  $customHolidays Optional associative array of ['Y-m-d' => 'Name'] or list of dates
     * @return float Net working days
     */
    public static function calculateBusinessDays(string $fromDate, string $toDate, bool $isHalfDay = false, array $customHolidays = []): float {
        $fromTs = strtotime($fromDate);
        $toTs = strtotime($toDate);

        if ($fromTs === false || $toTs === false || $toTs < $fromTs) {
            return 0.0;
        }

        if ($isHalfDay && $fromDate === $toDate) {
            $dayOfWeek = (int)date('N', $fromTs);
            // If it falls on weekend (6=Sat, 7=Sun), return 0.0
            if ($dayOfWeek >= 6) {
                return 0.0;
            }
            return 0.5;
        }

        $year = (int)date('Y', $fromTs);
        $statutory = self::getStatutoryHolidays($year);
        $allHolidays = array_merge($statutory, $customHolidays);

        $businessDays = 0.0;
        $currentTs = $fromTs;

        while ($currentTs <= $toTs) {
            $dayOfWeek = (int)date('N', $currentTs); // 1 (Mon) through 7 (Sun)
            $dateStr = date('Y-m-d', $currentTs);

            // Check if weekday (Mon-Fri) and not a holiday
            $isWeekend = ($dayOfWeek === 6 || $dayOfWeek === 7);
            $isHoliday = isset($allHolidays[$dateStr]) || in_array($dateStr, $allHolidays, true);

            if (!$isWeekend && !$isHoliday) {
                $businessDays += 1.0;
            }

            $currentTs = strtotime('+1 day', $currentTs);
        }

        if ($isHalfDay && $businessDays >= 1.0) {
            return 0.5;
        }

        return $businessDays;
    }

    /**
     * Check whether an employee has sufficient balance for a requested leave type.
     * 
     * @param object|array $balances Current balances object or array
     * @param string $leaveType 'Sick Leave', 'Casual Leave', 'Earned Leave'
     * @param float $requestedDays
     * @return array{allowed: bool, available: float, requested: float, remaining: float, error: ?string}
     */
    public static function checkLeaveBalance(object|array $balances, string $leaveType, float $requestedDays): array {
        $bObj = is_array($balances) ? (object)$balances : $balances;
        $available = 0.0;

        switch (strtolower(trim($leaveType))) {
            case 'sick leave':
            case 'sick':
                $available = (float)($bObj->sick_leave ?? 0.0);
                break;
            case 'casual leave':
            case 'casual':
                $available = (float)($bObj->casual_leave ?? 0.0);
                break;
            case 'earned leave':
            case 'earned':
            case 'privilege leave':
                $available = (float)($bObj->earned_leave ?? 0.0);
                break;
            case 'unpaid leave':
            case 'lop':
                // Loss of pay / unpaid leave is allowed without balance cap
                return [
                    'allowed'   => true,
                    'available' => 999.0,
                    'requested' => $requestedDays,
                    'remaining' => 999.0,
                    'error'     => null
                ];
            default:
                $available = (float)($bObj->casual_leave ?? 0.0);
                break;
        }

        if ($requestedDays <= 0) {
            return [
                'allowed'   => false,
                'available' => $available,
                'requested' => $requestedDays,
                'remaining' => $available,
                'error'     => 'Requested duration must be greater than zero working days.'
            ];
        }

        if ($requestedDays > $available) {
            return [
                'allowed'   => false,
                'available' => $available,
                'requested' => $requestedDays,
                'remaining' => $available,
                'error'     => "Insufficient leave balance for {$leaveType}. Available: {$available} day(s), Requested: {$requestedDays} day(s)."
            ];
        }

        return [
            'allowed'   => true,
            'available' => $available,
            'requested' => $requestedDays,
            'remaining' => round($available - $requestedDays, 2),
            'error'     => null
        ];
    }

    /**
     * Compute balance adjustments for approval or cancellation operations.
     */
    public static function adjustBalance(float $currentBalance, float $days, string $operation = 'deduct'): float {
        if ($operation === 'deduct') {
            return max(0.0, round($currentBalance - $days, 2));
        }
        if ($operation === 'credit') {
            return round($currentBalance + $days, 2);
        }
        return $currentBalance;
    }
}

