<?php
/**
 * MarketingJourney Model — Multi-Step Visual Automation Engine
 * -------------------------------------------------------------------------
 * Orchestrates event-driven customer journeys across email, WhatsApp,
 * lead scoring, and CRM stage progressions.
 */

class MarketingJourney extends Model {

    public const TRIGGERS = [
        'form_submitted' => 'Web Form Submitted',
        'email_clicked' => 'Marketing Email Clicked',
        'whatsapp_received' => 'WhatsApp Reply Received',
        'lead_created' => 'New Lead Ingested',
        'tag_added' => 'Tag Applied to Contact'
    ];

    public const STATUSES = ['draft', 'active', 'paused', 'archived'];

    public const STEP_TYPES = ['action', 'delay', 'condition'];

    public const ACTION_TYPES = [
        'send_email' => 'Send Marketing Email',
        'send_whatsapp' => 'Send WhatsApp Template',
        'adjust_lead_score' => 'Adjust Lead Score (+/-)',
        'update_stage' => 'Update Lifecycle Stage',
        'assign_rep' => 'Assign Lead to Rep',
        'wait_delay' => 'Wait Time Delay'
    ];

    public function getAll(?string $status = null): array {
        $sql = "SELECT j.*, 
                       (SELECT COUNT(*) FROM journey_steps s WHERE s.journey_id = j.journey_id) AS step_count
                FROM marketing_journeys j";
        if ($status) {
            $sql .= " WHERE j.status = :st";
        }
        $sql .= " ORDER BY j.created_at DESC";

        $this->query($sql);
        if ($status) {
            $this->bind(':st', $status);
        }
        return $this->resultSet() ?: [];
    }

    public function getById(int $journeyId): ?object {
        $this->query("SELECT * FROM marketing_journeys WHERE journey_id = :id LIMIT 1");
        $this->bind(':id', $journeyId);
        $row = $this->single();
        return $row ?: null;
    }

    public function getSteps(int $journeyId): array {
        $this->query("SELECT * FROM journey_steps WHERE journey_id = :jid ORDER BY step_order ASC");
        $this->bind(':jid', $journeyId);
        return $this->resultSet() ?: [];
    }

    public function create(array $data): int {
        $now = date('Y-m-d H:i:s');
        $this->query("INSERT INTO marketing_journeys (
            name, description, trigger_type, trigger_config_json, status, created_by, created_at, updated_at
        ) VALUES (
            :name, :desc, :trig, :tcfg, :stat, :uid, :now, :now
        )");

        $this->bind(':name', trim($data['name']));
        $this->bind(':desc', !empty($data['description']) ? trim($data['description']) : null);
        $this->bind(':trig', $data['trigger_type']);
        $this->bind(':tcfg', !empty($data['trigger_config']) ? json_encode($data['trigger_config']) : null);
        $this->bind(':stat', in_array($data['status'] ?? '', self::STATUSES, true) ? $data['status'] : 'draft');
        $this->bind(':uid', $data['created_by'] ?? null);
        $this->bind(':now', $now);

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public function update(int $journeyId, array $data): bool {
        $now = date('Y-m-d H:i:s');
        $this->query("UPDATE marketing_journeys SET
            name = :name,
            description = :desc,
            trigger_type = :trig,
            trigger_config_json = :tcfg,
            updated_at = :now
            WHERE journey_id = :id");

        $this->bind(':name', trim($data['name']));
        $this->bind(':desc', !empty($data['description']) ? trim($data['description']) : null);
        $this->bind(':trig', $data['trigger_type']);
        $this->bind(':tcfg', !empty($data['trigger_config']) ? json_encode($data['trigger_config']) : null);
        $this->bind(':now', $now);
        $this->bind(':id', $journeyId);

        return $this->execute();
    }

    public function updateStatus(int $journeyId, string $status): bool {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        $this->query("UPDATE marketing_journeys SET status = :st, updated_at = :now WHERE journey_id = :id");
        $this->bind(':st', $status);
        $this->bind(':now', date('Y-m-d H:i:s'));
        $this->bind(':id', $journeyId);
        return $this->execute();
    }

    public function setSteps(int $journeyId, array $steps): bool {
        $this->query("DELETE FROM journey_steps WHERE journey_id = :jid");
        $this->bind(':jid', $journeyId);
        $this->execute();

        $order = 1;
        foreach ($steps as $step) {
            $stepType = in_array($step['step_type'] ?? '', self::STEP_TYPES, true) ? $step['step_type'] : 'action';
            $actionType = $step['action_type'] ?? 'wait_delay';
            $config = !empty($step['action_config']) ? (is_string($step['action_config']) ? $step['action_config'] : json_encode($step['action_config'])) : null;

            $this->query("INSERT INTO journey_steps (journey_id, step_order, step_type, action_type, action_config_json)
                          VALUES (:jid, :sord, :stype, :atype, :acfg)");
            $this->bind(':jid', $journeyId);
            $this->bind(':sord', $order++);
            $this->bind(':stype', $stepType);
            $this->bind(':atype', $actionType);
            $this->bind(':acfg', $config);
            $this->execute();
        }
        return true;
    }

    public function enrollContact(int $journeyId, int $contactId, int $delaySeconds = 0): int {
        // Prevent duplicate active enrollments for same journey & contact
        $this->query("SELECT enrollment_id FROM journey_enrollments 
                      WHERE journey_id = :jid AND contact_id = :cid AND status = 'active' LIMIT 1");
        $this->bind(':jid', $journeyId);
        $this->bind(':cid', $contactId);
        $existing = $this->single();
        if ($existing) {
            return (int)$existing->enrollment_id;
        }

        $now = time();
        $nextRun = date('Y-m-d H:i:s', $now + $delaySeconds);

        $this->query("INSERT INTO journey_enrollments (
            journey_id, contact_id, current_step_order, status, next_run_at, enrolled_at
        ) VALUES (
            :jid, :cid, 1, 'active', :next_run, :enrolled_at
        )");

        $this->bind(':jid', $journeyId);
        $this->bind(':cid', $contactId);
        $this->bind(':next_run', $nextRun);
        $this->bind(':enrolled_at', date('Y-m-d H:i:s', $now));

        if ($this->execute()) {
            $enrollmentId = (int)$this->lastInsertId();

            // Increment total enrolled counter on journey
            $this->query("UPDATE marketing_journeys SET total_enrolled = total_enrolled + 1 WHERE journey_id = :jid");
            $this->bind(':jid', $journeyId);
            $this->execute();

            return $enrollmentId;
        }
        return 0;
    }

    public function getActiveJourneysForTrigger(string $triggerType): array {
        $this->query("SELECT * FROM marketing_journeys WHERE trigger_type = :trig AND status = 'active'");
        $this->bind(':trig', $triggerType);
        return $this->resultSet() ?: [];
    }

    public static function triggerEvent(string $triggerType, int $contactId, array $context = []): int {
        $journeyModel = new self();
        $activeJourneys = $journeyModel->getActiveJourneysForTrigger($triggerType);
        $enrolledCount = 0;

        foreach ($activeJourneys as $j) {
            // Check trigger filters if present (e.g. specific form_id)
            if (!empty($j->trigger_config_json)) {
                $tcfg = json_decode($j->trigger_config_json, true) ?: [];
                if (!empty($tcfg['form_id']) && !empty($context['form_id']) && (int)$tcfg['form_id'] !== (int)$context['form_id']) {
                    continue; // Skip if specific form required and doesn't match
                }
            }

            $enrolledId = $journeyModel->enrollContact((int)$j->journey_id, $contactId);
            if ($enrolledId > 0) {
                $enrolledCount++;
            }
        }
        return $enrolledCount;
    }
}

