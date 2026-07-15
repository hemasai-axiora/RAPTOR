<?php
// Raptor CRM Performance Scoring Model

class Performance extends Model {
    public const COMPONENTS = ['attendance','punctuality','activity','target','lead','followup','conversion','revenue','meeting','demo'];

    public function getWeights() {
        $this->query('SELECT * FROM scoring_weights WHERE active = TRUE ORDER BY label ASC');
        return $this->resultSet();
    }

    public function saveWeights(array $weights): void {
        foreach ($weights as $key => $value) {
            if (!in_array($key, self::COMPONENTS, true)) {
                continue;
            }
            $this->query('UPDATE scoring_weights SET weight_percent = :w WHERE weight_key = :k');
            $this->bind(':w', max(0, (float) $value));
            $this->bind(':k', $key);
            $this->execute();
        }
    }

    public function getScores(string $period, string $start, string $end, ?array $visibleUserIds = null) {
        [$where, $params] = $this->scopeWhere($visibleUserIds, 'ps.user_id');
        $where[] = 'ps.period = :period AND ps.start_date = :start AND ps.end_date = :end';
        $params[':period'] = $period;
        $params[':start'] = $start;
        $params[':end'] = $end;

        $this->query('SELECT ps.*, u.name AS user_name, t.name AS team_name
                      FROM performance_scores ps
                      JOIN users u ON ps.user_id = u.user_id
                      LEFT JOIN employees e ON u.user_id = e.user_id
                      LEFT JOIN teams t ON e.team_id = t.team_id
                      WHERE ' . implode(' AND ', $where) . '
                      ORDER BY ps.overall_score DESC, u.name ASC');
        $this->bindParams($params);
        return $this->resultSet();
    }

    public function getLatestForUser(int $userId, string $period = 'weekly') {
        $this->query('SELECT ps.*, u.name AS user_name
                      FROM performance_scores ps
                      JOIN users u ON ps.user_id = u.user_id
                      WHERE ps.user_id = :uid AND ps.period = :period
                      ORDER BY ps.end_date DESC LIMIT 1');
        $this->bind(':uid', $userId);
        $this->bind(':period', $period);
        return $this->single();
    }

    public function getReviews(int $userId, string $period, string $start, string $end) {
        $this->query('SELECT r.*, u.name AS reviewer_name
                      FROM manager_reviews r
                      JOIN users u ON r.reviewer_user_id = u.user_id
                      WHERE r.user_id = :uid AND r.period = :period AND r.start_date = :start AND r.end_date = :end
                      ORDER BY r.created_at DESC');
        $this->bind(':uid', $userId);
        $this->bind(':period', $period);
        $this->bind(':start', $start);
        $this->bind(':end', $end);
        return $this->resultSet();
    }

    public function addReview(array $data): bool {
        $this->query('INSERT INTO manager_reviews
            (user_id, reviewer_user_id, period, start_date, end_date, rating, remarks)
            VALUES (:uid, :reviewer, :period, :start, :end, :rating, :remarks)');
        $this->bind(':uid', (int) $data['user_id']);
        $this->bind(':reviewer', (int) $data['reviewer_user_id']);
        $this->bind(':period', $data['period']);
        $this->bind(':start', $data['start_date']);
        $this->bind(':end', $data['end_date']);
        $this->bind(':rating', $data['rating'] !== '' ? (int) $data['rating'] : null);
        $this->bind(':remarks', $data['remarks'] ?? null);
        return $this->execute();
    }

    public function recompute(string $period = 'weekly', ?string $start = null, ?string $end = null): int {
        [$start, $end] = $this->periodRange($period, $start, $end);
        $weights = $this->weightMap();
        $users = $this->scoreUsers();
        $count = 0;

        foreach ($users as $user) {
            $uid = (int) $user->user_id;
            $components = [
                'attendance' => $this->attendanceScore($uid, $start, $end),
                'punctuality' => $this->punctualityScore($uid, $start, $end),
                'activity' => $this->activityScore($uid, $start, $end),
                'target' => $this->targetScore($uid, $start, $end),
                'lead' => $this->leadScore($uid, $start, $end),
                'followup' => $this->followupScore($uid, $start, $end),
                'conversion' => $this->conversionScore($uid, $start, $end),
                'revenue' => $this->revenueScore($uid, $start, $end),
                'meeting' => $this->meetingScore($uid, $start, $end, 'meeting'),
                'demo' => $this->meetingScore($uid, $start, $end, 'demo'),
            ];

            $overall = 0.0;
            $totalWeight = 0.0;
            foreach ($components as $key => $score) {
                $weight = $weights[$key] ?? 0.0;
                $overall += $score * $weight;
                $totalWeight += $weight;
            }
            $overall = $totalWeight > 0 ? round($overall / $totalWeight, 2) : 0.0;
            $band = $this->band($overall);

            $this->query('INSERT INTO performance_scores
                (user_id, period, start_date, end_date, attendance_score, punctuality_score, activity_score,
                 target_score, lead_score, followup_score, conversion_score, revenue_score, meeting_score, demo_score,
                 overall_score, performance_band)
                VALUES
                (:uid, :period, :start, :end, :attendance, :punctuality, :activity,
                 :target, :lead, :followup, :conversion, :revenue, :meeting, :demo,
                 :overall, :band)
                ON DUPLICATE KEY UPDATE
                 attendance_score = :attendance2, punctuality_score = :punctuality2, activity_score = :activity2,
                 target_score = :target2, lead_score = :lead2, followup_score = :followup2,
                 conversion_score = :conversion2, revenue_score = :revenue2, meeting_score = :meeting2,
                 demo_score = :demo2, overall_score = :overall2, performance_band = :band2');
            $this->bind(':uid', $uid);
            $this->bind(':period', $period);
            $this->bind(':start', $start);
            $this->bind(':end', $end);
            foreach ($components as $key => $score) {
                $this->bind(':' . $key, $score);
                $this->bind(':' . $key . '2', $score);
            }
            $this->bind(':overall', $overall);
            $this->bind(':overall2', $overall);
            $this->bind(':band', $band);
            $this->bind(':band2', $band);
            $this->execute();
            $count++;
        }

        $this->updateRanks($period, $start, $end);
        return $count;
    }

    public function periodRange(string $period, ?string $start = null, ?string $end = null): array {
        if ($start && $end) {
            return [$start, $end];
        }
        if ($period === 'monthly') {
            return [date('Y-m-01'), date('Y-m-t')];
        }
        return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
    }

    private function scoreUsers(): array {
        $this->query('SELECT u.user_id
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      WHERE u.status = "active" AND r.role_name IN ("employee","sales_person","team_leader")');
        return $this->resultSet();
    }

    private function weightMap(): array {
        $weights = [];
        foreach ($this->getWeights() as $row) {
            $weights[$row->weight_key] = (float) $row->weight_percent;
        }
        return $weights;
    }

    private function attendanceScore(int $uid, string $start, string $end): float {
        $days = max(1, $this->workdays($start, $end));
        $present = $this->scalar('SELECT COUNT(*) FROM attendance WHERE user_id = :uid AND work_date BETWEEN :start AND :end AND status IN ("present","half_day")', $uid, $start, $end);
        return min(100, round(($present / $days) * 100, 2));
    }

    private function punctualityScore(int $uid, string $start, string $end): float {
        $present = $this->scalar('SELECT COUNT(*) FROM attendance WHERE user_id = :uid AND work_date BETWEEN :start AND :end AND status IN ("present","half_day")', $uid, $start, $end);
        if ($present <= 0) { return 0.0; }
        $late = $this->scalar('SELECT COUNT(*) FROM attendance WHERE user_id = :uid AND work_date BETWEEN :start AND :end AND is_late = 1', $uid, $start, $end);
        return max(0, round((1 - ($late / $present)) * 100, 2));
    }

    private function activityScore(int $uid, string $start, string $end): float {
        $comms = $this->scalarDateTime('SELECT COUNT(*) FROM communications WHERE user_id = :uid AND happened_at BETWEEN :start AND :end', $uid, $start, $end);
        $tasks = $this->scalarDateTime('SELECT COUNT(*) FROM tasks WHERE assigned_to_user_id = :uid AND review_status = "approved" AND COALESCE(reviewed_at, completed_at, updated_at) BETWEEN :start AND :end', $uid, $start, $end);
        return min(100, round((($comms / 30) * 70) + (($tasks / 10) * 30), 2));
    }

    private function targetScore(int $uid, string $start, string $end): float {
        $this->query('SELECT AVG(tp.completion_percent)
                      FROM targets t
                      JOIN target_items ti ON t.target_id = ti.target_id
                      JOIN target_progress tp ON ti.target_item_id = tp.target_item_id
                      WHERE t.owner_type = "employee" AND t.owner_user_id = :uid
                        AND t.status = "approved" AND t.start_date <= :end AND t.end_date >= :start');
        $this->bind(':uid', $uid);
        $this->bind(':start', $start);
        $this->bind(':end', $end);
        $this->execute();
        return min(100, (float) ($this->stmt->fetchColumn() ?: 0));
    }

    private function leadScore(int $uid, string $start, string $end): float {
        $count = $this->scalarDateTime('SELECT COUNT(*) FROM leads WHERE assigned_to_user_id = :uid AND created_at BETWEEN :start AND :end', $uid, $start, $end);
        return min(100, round(($count / 20) * 100, 2));
    }

    private function followupScore(int $uid, string $start, string $end): float {
        $total = $this->scalarDateTime('SELECT COUNT(*) FROM follow_ups WHERE assigned_to_user_id = :uid AND due_at BETWEEN :start AND :end', $uid, $start, $end);
        if ($total <= 0) { return 100.0; }
        $good = $this->scalarDateTime('SELECT COUNT(*) FROM follow_ups WHERE assigned_to_user_id = :uid AND due_at BETWEEN :start AND :end AND status IN ("completed","scheduled")', $uid, $start, $end);
        return round(($good / $total) * 100, 2);
    }

    private function conversionScore(int $uid, string $start, string $end): float {
        $count = $this->scalarDateTime('SELECT COUNT(*) FROM leads WHERE assigned_to_user_id = :uid AND status = "converted" AND COALESCE(converted_at, updated_at) BETWEEN :start AND :end', $uid, $start, $end);
        return min(100, round(($count / 5) * 100, 2));
    }

    private function revenueScore(int $uid, string $start, string $end): float {
        $revenue = $this->scalarDateTime('SELECT COALESCE(SUM(lead_value),0) FROM leads WHERE assigned_to_user_id = :uid AND status = "converted" AND COALESCE(converted_at, updated_at) BETWEEN :start AND :end', $uid, $start, $end);
        return min(100, round(($revenue / 100000) * 100, 2));
    }

    private function meetingScore(int $uid, string $start, string $end, string $type): float {
        $count = $this->scalarDateTime('SELECT COUNT(*) FROM meetings WHERE assigned_to_user_id = :uid AND type = "' . $type . '" AND status = "completed" AND scheduled_start BETWEEN :start AND :end', $uid, $start, $end);
        return min(100, round(($count / 8) * 100, 2));
    }

    private function scalar(string $sql, int $uid, string $start, string $end): float {
        $this->query($sql);
        $this->bind(':uid', $uid);
        $this->bind(':start', $start);
        $this->bind(':end', $end);
        $this->execute();
        return (float) $this->stmt->fetchColumn();
    }

    private function scalarDateTime(string $sql, int $uid, string $start, string $end): float {
        return $this->scalar($sql, $uid, $start . ' 00:00:00', $end . ' 23:59:59');
    }

    private function updateRanks(string $period, string $start, string $end): void {
        $this->query('SELECT ps.score_id, ps.user_id, e.team_id, ps.overall_score
                      FROM performance_scores ps
                      LEFT JOIN employees e ON ps.user_id = e.user_id
                      WHERE ps.period = :period AND ps.start_date = :start AND ps.end_date = :end
                      ORDER BY e.team_id ASC, ps.overall_score DESC');
        $this->bind(':period', $period);
        $this->bind(':start', $start);
        $this->bind(':end', $end);
        $rows = $this->resultSet();
        $rankByTeam = [];
        foreach ($rows as $row) {
            $team = $row->team_id ?: 'none';
            $rankByTeam[$team] = ($rankByTeam[$team] ?? 0) + 1;
            $this->query('UPDATE performance_scores SET team_rank = :rank WHERE score_id = :id');
            $this->bind(':rank', $rankByTeam[$team]);
            $this->bind(':id', (int) $row->score_id);
            $this->execute();
        }
    }

    private function workdays(string $start, string $end): int {
        $days = 0;
        $cur = strtotime($start);
        $last = strtotime($end);
        while ($cur <= $last) {
            if ((int) date('N', $cur) <= 6) { $days++; }
            $cur = strtotime('+1 day', $cur);
        }
        return $days;
    }

    private function band(float $score): string {
        if ($score >= 85) { return 'excellent'; }
        if ($score >= 70) { return 'good'; }
        if ($score >= 50) { return 'average'; }
        return 'needs_attention';
    }

    private function scopeWhere(?array $visibleUserIds, string $column): array {
        if ($visibleUserIds === null) { return [[], []]; }
        if (!$visibleUserIds) { return [['1 = 0'], []]; }
        $ids = implode(',', array_map('intval', $visibleUserIds));
        return [[$column . ' IN (' . $ids . ')'], []];
    }

    private function bindParams(array $params): void {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }
}
