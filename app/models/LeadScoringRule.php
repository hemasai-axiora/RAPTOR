<?php
/**
 * LeadScoringRule Model — Behavioral Point Engine & Quality Tier Progression
 * -------------------------------------------------------------------------
 * Dynamically awards points based on prospect activities (form submissions,
 * email clicks, WhatsApp engagement, high-intent pageviews) and transitions
 * lead quality between Cold, Warm, and Hot.
 */

class LeadScoringRule extends Model {

    public const STANDARD_EVENTS = [
        'form_submit' => 'Web Form Submission (+25)',
        'email_open' => 'Email Opened (+5)',
        'email_click' => 'Email Link Clicked (+15)',
        'whatsapp_reply' => 'WhatsApp Reply (+20)',
        'pageview_high_intent' => 'High Intent Page Visit (+10)',
        'inbound_call' => 'Telephony Call Connected (+30)'
    ];

    public function getAll(): array {
        $this->query("SELECT * FROM lead_scoring_rules ORDER BY score_delta DESC");
        return $this->resultSet() ?: [];
    }

    public function getByEventType(string $eventType): ?object {
        $this->query("SELECT * FROM lead_scoring_rules WHERE event_type = :evt AND is_active = 1 LIMIT 1");
        $this->bind(':evt', trim($eventType));
        $row = $this->single();
        return $row ?: null;
    }

    public function saveRule(string $name, string $eventType, int $delta, int $maxDaily = 5): bool {
        $this->query("INSERT INTO lead_scoring_rules (rule_name, event_type, score_delta, max_daily_occurrences, is_active)
                      VALUES (:name, :evt, :delta, :max, 1)
                      ON DUPLICATE KEY UPDATE rule_name = :name, score_delta = :delta, max_daily_occurrences = :max, is_active = 1");
        $this->bind(':name', trim($name));
        $this->bind(':evt', trim($eventType));
        $this->bind(':delta', $delta);
        $this->bind(':max', max(1, $maxDaily));
        return $this->execute();
    }

    public function recordEventScore(int $contactId, string $eventType, ?string $customReason = null): int {
        $rule = $this->getByEventType($eventType);
        if (!$rule) {
            return 0; // No active rule for this event
        }

        // 1. Check daily frequency cap
        $todayStart = date('Y-m-d 00:00:00');
        $this->query("SELECT COUNT(*) AS cnt FROM lead_score_logs 
                      WHERE contact_id = :cid AND rule_id = :rid AND occurred_at >= :start");
        $this->bind(':cid', $contactId);
        $this->bind(':rid', (int)$rule->rule_id);
        $this->bind(':start', $todayStart);
        $dailyCount = (int)($this->single()->cnt ?? 0);

        if ($dailyCount >= (int)$rule->max_daily_occurrences) {
            return 0; // Frequency limit reached for today
        }

        // 2. Fetch current contact score
        $contactModel = new Contact();
        $contact = $contactModel->getById($contactId);
        if (!$contact) {
            return 0;
        }

        $prevScore = (int)($contact->lead_score ?? 0);
        $delta = (int)$rule->score_delta;
        $newScore = max(0, $prevScore + $delta);

        // 3. Derive quality tier
        $newQuality = 'cold';
        if ($newScore >= 50) {
            $newQuality = 'hot';
        } elseif ($newScore >= 15) {
            $newQuality = 'warm';
        }

        // 4. Update contact profile
        $this->query("UPDATE contacts SET lead_score = :nscore, lead_quality = :nqual, updated_at = :now WHERE contact_id = :cid");
        $this->bind(':nscore', $newScore);
        $this->bind(':nqual', $newQuality);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':cid', $contactId);
        $this->execute();

        // 5. Log audit trail entry
        $reason = $customReason ?: $rule->rule_name;
        $this->query("INSERT INTO lead_score_logs (contact_id, rule_id, score_delta, previous_score, new_score, reason, occurred_at)
                      VALUES (:cid, :rid, :delta, :prev, :new, :reason, :now)");
        $this->bind(':cid', $contactId);
        $this->bind(':rid', (int)$rule->rule_id);
        $this->bind(':delta', $delta);
        $this->bind(':prev', $prevScore);
        $this->bind(':new', $newScore);
        $this->bind(':reason', $reason);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->execute();

        // 6. Record activity in Customer 360 timeline
        try {
            $contactModel->recordActivity(
                $contactId,
                'crm',
                'lead_score_adjusted',
                "Score Adjusted: " . ($delta >= 0 ? "+{$delta}" : "{$delta}") . " ({$newQuality})",
                "Event [{$eventType}]: {$reason}. Total score is now {$newScore}.",
                ['event_type' => $eventType, 'delta' => $delta, 'new_score' => $newScore]
            );
        } catch (Throwable $e) {}

        return $newScore;
    }
}

