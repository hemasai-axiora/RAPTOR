<?php
/**
 * TelephonyCall Model — Call Log, Dispositions & Recording Registry
 * -------------------------------------------------------------------------
 * Tracks inbound and outbound telephony calls, disposition outcome tagging,
 * audio recording playback links, and sales performance analytics.
 */

class TelephonyCall extends Model {

    public const STATUSES = ['initiated', 'ringing', 'in_progress', 'completed', 'busy', 'no_answer', 'failed'];

    public function getCalls(array $filters = [], int $limit = 50, int $offset = 0): array {
        $conds = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conds[] = "c.status = :st";
            $params[':st'] = $filters['status'];
        }

        if (!empty($filters['user_id'])) {
            $conds[] = "c.user_id = :uid";
            $params[':uid'] = (int)$filters['user_id'];
        }

        if (!empty($filters['contact_id'])) {
            $conds[] = "c.contact_id = :cid";
            $params[':cid'] = (int)$filters['contact_id'];
        }

        if (!empty($filters['search'])) {
            $conds[] = "(c.to_number LIKE :srch OR c.from_number LIKE :srch OR ct.first_name LIKE :srch OR ct.last_name LIKE :srch)";
            $params[':srch'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = !empty($conds) ? 'WHERE ' . implode(' AND ', $conds) : '';

        $sql = "SELECT c.*, u.name AS agent_name, 
                       ct.first_name, ct.last_name, ct.company_name, ct.primary_email
                FROM telephony_calls c
                LEFT JOIN users u ON c.user_id = u.user_id
                LEFT JOIN contacts ct ON c.contact_id = ct.contact_id
                {$whereSql}
                ORDER BY c.started_at DESC
                LIMIT :limit OFFSET :offset";

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $this->bind(':limit', max(1, $limit));
        $this->bind(':offset', max(0, $offset));

        return $this->resultSet() ?: [];
    }

    public function getById(int $callId): ?object {
        $this->query("SELECT c.*, u.name AS agent_name, 
                             ct.first_name, ct.last_name, ct.company_name, ct.primary_email, ct.is_suppressed
                      FROM telephony_calls c
                      LEFT JOIN users u ON c.user_id = u.user_id
                      LEFT JOIN contacts ct ON c.contact_id = ct.contact_id
                      WHERE c.call_id = :id LIMIT 1");
        $this->bind(':id', $callId);
        $row = $this->single();
        return $row ?: null;
    }

    public function getByCallSid(string $callSid): ?object {
        $this->query("SELECT * FROM telephony_calls WHERE call_sid = :sid LIMIT 1");
        $this->bind(':sid', $callSid);
        $row = $this->single();
        return $row ?: null;
    }

    public function updateDisposition(int $callId, string $code, string $notes, ?string $callbackDueAt = null): bool {
        $call = $this->getById($callId);
        if (!$call) return false;

        $this->query("UPDATE telephony_calls 
                      SET disposition_code = :code, disposition_notes = :notes 
                      WHERE call_id = :id");
        $this->bind(':code', trim($code));
        $this->bind(':notes', trim($notes));
        $this->bind(':id', $callId);
        $ok = $this->execute();

        if ($ok && !empty($call->contact_id)) {
            try {
                $contactModel = new Contact();
                $contactModel->recordActivity(
                    (int)$call->contact_id,
                    'call',
                    'call_disposition',
                    "Call Disposition: " . strtoupper($code),
                    trim($notes),
                    ['call_id' => $callId, 'disposition' => $code, 'callback_due_at' => $callbackDueAt],
                    !empty($call->user_id) ? (int)$call->user_id : null
                );

                // If scheduled callback requested and lead_id attached, create follow-up
                if ($code === 'callback_requested' && !empty($call->lead_id) && $callbackDueAt) {
                    $this->query("INSERT INTO follow_ups (lead_id, status, due_at, created_at)
                                  VALUES (:lid, 'scheduled', :due, :now)");
                    $this->bind(':lid', (int)$call->lead_id);
                    $this->bind(':due', $callbackDueAt);
                    $this->bind(':now', date('Y-m-d H:i:s'));
                    $this->execute();
                }
            } catch (Throwable $e) {}
        }

        return $ok;
    }

    public function getAnalytics(): array {
        $this->query("SELECT 
            COUNT(*) AS total_calls,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_calls,
            SUM(duration_seconds) AS total_duration_seconds,
            ROUND(AVG(CASE WHEN status = 'completed' THEN duration_seconds ELSE NULL END), 1) AS avg_duration_seconds
        FROM telephony_calls");
        $metrics = $this->single();

        $totalCalls = (int)($metrics->total_calls ?? 0);
        $completedCalls = (int)($metrics->completed_calls ?? 0);
        $answerRate = $totalCalls > 0 ? round(($completedCalls / $totalCalls) * 100, 1) : 0;
        $totalMinutes = round(((int)($metrics->total_duration_seconds ?? 0)) / 60, 1);

        // Disposition breakdown
        $this->query("SELECT disposition_code, COUNT(*) AS cnt 
                      FROM telephony_calls 
                      WHERE disposition_code IS NOT NULL 
                      GROUP BY disposition_code");
        $dispositions = $this->resultSet() ?: [];

        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completedCalls,
            'answer_rate' => $answerRate,
            'total_talk_minutes' => $totalMinutes,
            'avg_duration_seconds' => (float)($metrics->avg_duration_seconds ?? 0),
            'dispositions' => $dispositions
        ];
    }
}

