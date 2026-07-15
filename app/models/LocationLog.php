<?php
// Raptor CRM — Location tracking model (Sprint 4)

class LocationLog extends Model {

    /** A user is "on duty" if today's attendance is checked in but not out. */
    public function isOnDuty($userId): bool {
        $this->query('SELECT 1 FROM attendance
                      WHERE user_id = :uid AND work_date = :d
                        AND login_at IS NOT NULL AND logout_at IS NULL LIMIT 1');
        $this->bind(':uid', (int) $userId);
        $this->bind(':d', date('Y-m-d'));
        return (bool) $this->single();
    }

    private function setting($key, $default) {
        $this->query('SELECT setting_value FROM settings WHERE setting_key = :k');
        $this->bind(':k', $key);
        $row = $this->single();
        return $row ? $row->setting_value : $default;
    }

    /**
     * Store a batch of location points — ONLY while on duty and if tracking is
     * enabled. Silently drops points that are off-duty or too inaccurate.
     *
     * @param array $points  each: ['lat','lng','accuracy'(opt),'ts'(opt epoch ms),'source'(opt)]
     * @return array ['on_duty'=>bool,'stored'=>int]
     */
    public function ping($userId, array $points): array {
        if ((string) $this->setting('location.tracking_enabled', '1') !== '1') {
            return ['on_duty' => false, 'stored' => 0];
        }
        if (!$this->isOnDuty($userId)) {
            return ['on_duty' => false, 'stored' => 0];
        }

        $maxAcc = (int) $this->setting('location.max_accuracy_m', 150);
        $points = array_slice($points, 0, 50); // guard against oversized batches
        $stored = 0;

        foreach ($points as $p) {
            if (!isset($p['lat'], $p['lng']) || !is_numeric($p['lat']) || !is_numeric($p['lng'])) {
                continue;
            }
            $acc = isset($p['accuracy']) && is_numeric($p['accuracy']) ? (int) $p['accuracy'] : null;
            if ($acc !== null && $maxAcc > 0 && $acc > $maxAcc) {
                continue; // too imprecise to be useful
            }
            // Prefer client timestamp when sane, else server time.
            $ts = date('Y-m-d H:i:s');
            if (isset($p['ts']) && is_numeric($p['ts'])) {
                $sec = (int) ($p['ts'] / 1000);
                if ($sec > 0 && $sec <= time() + 300) { $ts = date('Y-m-d H:i:s', $sec); }
            }
            $source = in_array($p['source'] ?? '', ['periodic','checkin','manual','meeting']) ? $p['source'] : 'periodic';

            $this->query('INSERT INTO location_logs (user_id, captured_at, lat, lng, accuracy_m, source)
                          VALUES (:uid, :ts, :lat, :lng, :acc, :src)');
            $this->bind(':uid', (int) $userId);
            $this->bind(':ts', $ts);
            $this->bind(':lat', (float) $p['lat']);
            $this->bind(':lng', (float) $p['lng']);
            $this->bind(':acc', $acc);
            $this->bind(':src', $source);
            if ($this->execute()) { $stored++; }
        }

        return ['on_duty' => true, 'stored' => $stored];
    }

    /** All points for a user on a date, ordered chronologically. */
    public function getDayPoints($userId, $date) {
        $this->query('SELECT lat, lng, accuracy_m, source, captured_at
                      FROM location_logs
                      WHERE user_id = :uid AND DATE(captured_at) = :d
                      ORDER BY captured_at ASC');
        $this->bind(':uid', (int) $userId);
        $this->bind(':d', $date);
        return $this->resultSet();
    }

    /** Attendance login/logout pins for the day (for map markers). */
    public function getAttendancePins($userId, $date) {
        $this->query('SELECT login_lat, login_lng, logout_lat, logout_lng, login_at, logout_at
                      FROM attendance WHERE user_id = :uid AND work_date = :d');
        $this->bind(':uid', (int) $userId);
        $this->bind(':d', $date);
        return $this->single();
    }

    /** Compute total distance (km) from an ordered point list. */
    public function distanceKm(array $points): float {
        $km = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $km += $this->haversineMeters(
                (float) $points[$i - 1]->lat, (float) $points[$i - 1]->lng,
                (float) $points[$i]->lat,     (float) $points[$i]->lng
            ) / 1000.0;
        }
        return round($km, 2);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $R = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ---------------- Cron rollup ----------------

    /** Distinct user IDs that logged any point on the given date. */
    public function getUsersWithPointsOn($date): array {
        $this->query('SELECT DISTINCT user_id FROM location_logs WHERE DATE(captured_at) = :d');
        $this->bind(':d', $date);
        $this->execute();
        return array_map('intval', $this->stmt->fetchAll(PDO::FETCH_COLUMN, 0));
    }

    /** Compute and upsert a user's daily travel summary. Returns distance_km. */
    public function rollupDay($userId, $date): float {
        $points = $this->getDayPoints($userId, $date);
        $count  = count($points);
        $dist   = $this->distanceKm($points);

        $polyline = [];
        foreach ($points as $p) { $polyline[] = [(float) $p->lat, (float) $p->lng]; }
        $first = $count ? $points[0]->captured_at : null;
        $last  = $count ? $points[$count - 1]->captured_at : null;

        $this->query('INSERT INTO travel_summary
                        (user_id, work_date, distance_km, points_count, first_at, last_at, route_polyline)
                      VALUES (:uid, :d, :dist, :cnt, :first, :last, :poly)
                      ON DUPLICATE KEY UPDATE
                        distance_km = :dist2, points_count = :cnt2,
                        first_at = :first2, last_at = :last2, route_polyline = :poly2');
        $this->bind(':uid', (int) $userId);
        $this->bind(':d', $date);
        $this->bind(':dist', $dist);
        $this->bind(':cnt', $count, PDO::PARAM_INT);
        $this->bind(':first', $first);
        $this->bind(':last', $last);
        $this->bind(':poly', json_encode($polyline));
        $this->bind(':dist2', $dist);
        $this->bind(':cnt2', $count, PDO::PARAM_INT);
        $this->bind(':first2', $first);
        $this->bind(':last2', $last);
        $this->bind(':poly2', json_encode($polyline));
        $this->execute();

        return $dist;
    }

    /** Retention hook. Physical deletion is disabled by governance policy. */
    public function purgeOldPoints(): int {
        return 0;
    }
}
