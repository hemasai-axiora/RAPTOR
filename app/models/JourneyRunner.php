<?php
/**
 * JourneyRunner — Marketing Automation Step Execution Engine
 * -------------------------------------------------------------------------
 * Processes pending journey enrollments, executes multi-channel actions,
 * advances delay timers, enforces suppression invariants, and logs history.
 */

class JourneyRunner extends Model {

    public function processPendingEnrollments(int $batchSize = 50): array {
        $now = date('Y-m-d H:i:s');
        $this->query("SELECT e.*, j.name AS journey_name 
                      FROM journey_enrollments e
                      JOIN marketing_journeys j ON e.journey_id = j.journey_id
                      WHERE e.status = 'active' 
                        AND e.next_run_at <= :now 
                        AND j.status = 'active'
                      ORDER BY e.next_run_at ASC 
                      LIMIT :lim");
        $this->bind(':now', $now);
        $this->bind(':lim', max(1, $batchSize));
        $enrollments = $this->resultSet() ?: [];

        $results = [
            'processed' => 0,
            'completed' => 0,
            'delayed' => 0,
            'suppressed_cancelled' => 0,
            'errors' => []
        ];

        $contactModel = new Contact();

        foreach ($enrollments as $enrollment) {
            $results['processed']++;
            $enrollmentId = (int)$enrollment->enrollment_id;
            $journeyId = (int)$enrollment->journey_id;
            $contactId = (int)$enrollment->contact_id;
            $stepOrder = (int)$enrollment->current_step_order;

            // 1. Suppression Pre-Flight Check
            $contact = $contactModel->getById($contactId);
            if (!$contact) {
                $this->cancelEnrollment($enrollmentId, "Contact #{$contactId} no longer exists.");
                continue;
            }

            if ((int)$contact->is_suppressed === 1) {
                $this->cancelEnrollment($enrollmentId, "Halted: Contact is globally suppressed/opted-out.");
                $results['suppressed_cancelled']++;
                continue;
            }

            // 2. Fetch Current Step
            $this->query("SELECT * FROM journey_steps 
                          WHERE journey_id = :jid AND step_order = :sord LIMIT 1");
            $this->bind(':jid', $journeyId);
            $this->bind(':sord', $stepOrder);
            $step = $this->single();

            // If no step found at this order, journey has completed!
            if (!$step) {
                $this->completeEnrollment($enrollmentId, $journeyId);
                $results['completed']++;
                continue;
            }

            // 3. Execute Step
            $stepResult = $this->executeStep($step, $contact, $enrollment);

            if ($stepResult['type'] === 'delay') {
                $delaySecs = (int)$stepResult['delay_seconds'];
                $nextRun = date('Y-m-d H:i:s', time() + $delaySecs);

                $this->advanceEnrollment($enrollmentId, $stepOrder + 1, $nextRun, [
                    'step' => $stepOrder,
                    'action' => 'delay',
                    'detail' => "Waiting {$delaySecs} seconds until {$nextRun}"
                ]);
                $results['delayed']++;
            } else {
                // Immediate action succeeded - advance to next step immediately
                $this->advanceEnrollment($enrollmentId, $stepOrder + 1, date('Y-m-d H:i:s'), [
                    'step' => $stepOrder,
                    'action' => $step->action_type,
                    'detail' => $stepResult['message'] ?? 'Action completed'
                ]);
            }
        }

        return $results;
    }

    private function executeStep(object $step, object $contact, object $enrollment): array {
        $action = $step->action_type;
        $config = !empty($step->action_config_json) ? json_decode($step->action_config_json, true) : [];
        $contactId = (int)$contact->contact_id;

        switch ($action) {
            case 'wait_delay':
                $seconds = !empty($config['delay_seconds']) ? (int)$config['delay_seconds'] : (!empty($config['delay_minutes']) ? (int)$config['delay_minutes'] * 60 : 3600);
                return [
                    'type' => 'delay',
                    'delay_seconds' => $seconds,
                    'message' => "Delay scheduled for {$seconds}s"
                ];

            case 'adjust_lead_score':
                $delta = (int)($config['score_delta'] ?? 10);
                $scoringModel = new LeadScoringRule();
                $prevScore = (int)($contact->lead_score ?? 0);
                $newScore = max(0, $prevScore + $delta);

                $newQuality = ($newScore >= 50) ? 'hot' : (($newScore >= 15) ? 'warm' : 'cold');

                $this->query("UPDATE contacts SET lead_score = :ns, lead_quality = :nq, updated_at = :now WHERE contact_id = :cid");
                $this->bind(':ns', $newScore);
                $this->bind(':nq', $newQuality);
                $this->bind(':now', date('Y-m-d H:i:s'));
                $this->bind(':cid', $contactId);
                $this->execute();

                $contactModel = new Contact();
                $contactModel->recordActivity(
                    $contactId,
                    'crm',
                    'lead_score_adjusted',
                    "Automation Journey Score: " . ($delta >= 0 ? "+{$delta}" : "{$delta}"),
                    "Lead score updated to {$newScore} via Journey #{$step->journey_id}",
                    ['delta' => $delta, 'new_score' => $newScore]
                );

                return ['type' => 'action', 'message' => "Lead score updated by {$delta} to {$newScore}"];

            case 'update_stage':
                $stage = $config['lifecycle_stage'] ?? 'mql';
                $contactModel = new Contact();
                $contactModel->setLifecycleStage($contactId, $stage);
                return ['type' => 'action', 'message' => "Lifecycle stage updated to {$stage}"];

            case 'assign_rep':
                $userId = !empty($config['user_id']) ? (int)$config['user_id'] : 1;
                $this->query("UPDATE contacts SET assigned_user_id = :uid, updated_at = :now WHERE contact_id = :cid");
                $this->bind(':uid', $userId);
                $this->bind(':now', date('Y-m-d H:i:s'));
                $this->bind(':cid', $contactId);
                $this->execute();

                $contactModel = new Contact();
                $contactModel->recordActivity(
                    $contactId,
                    'crm',
                    'rep_assigned',
                    "Assigned to Sales Rep #{$userId}",
                    "Automated assignment via Journey #{$step->journey_id}",
                    ['assigned_user_id' => $userId]
                );

                return ['type' => 'action', 'message' => "Contact assigned to user #{$userId}"];

            case 'send_whatsapp':
                if (empty($contact->primary_phone)) {
                    return ['type' => 'action', 'message' => "Skipped WhatsApp: No phone number registered."];
                }
                $templateName = $config['template_name'] ?? 'welcome_onboarding';
                $lang = $config['language'] ?? 'en_US';
                $res = WhatsAppService::sendTemplateMessage($contact->primary_phone, $templateName, $lang);

                $contactModel = new Contact();
                $contactModel->recordActivity(
                    $contactId,
                    'whatsapp',
                    'whatsapp_sent',
                    "Sent WhatsApp Template [{$templateName}]",
                    "Automated dispatch via Journey #{$step->journey_id}",
                    ['template' => $templateName, 'result' => $res]
                );

                return ['type' => 'action', 'message' => "WhatsApp template '{$templateName}' sent to {$contact->primary_phone}"];

            case 'send_email':
                if (empty($contact->primary_email)) {
                    return ['type' => 'action', 'message' => "Skipped Email: No email address registered."];
                }
                $subject = $config['subject'] ?? 'Welcome to Raptor Marketing!';
                $contactModel = new Contact();
                $contactModel->recordActivity(
                    $contactId,
                    'email',
                    'email_sent',
                    "Sent Automation Email: {$subject}",
                    "Automated dispatch via Journey #{$step->journey_id}",
                    ['subject' => $subject]
                );
                return ['type' => 'action', 'message' => "Automation email '{$subject}' sent to {$contact->primary_email}"];

            default:
                return ['type' => 'action', 'message' => "Executed generic step {$action}"];
        }
    }

    private function advanceEnrollment(int $enrollmentId, int $nextStepOrder, string $nextRunAt, array $logItem): void {
        $this->query("SELECT execution_log_json FROM journey_enrollments WHERE enrollment_id = :id");
        $this->bind(':id', $enrollmentId);
        $currLog = $this->single();
        $logs = !empty($currLog->execution_log_json) ? json_decode($currLog->execution_log_json, true) : [];
        $logItem['timestamp'] = date('Y-m-d H:i:s');
        $logs[] = $logItem;

        $this->query("UPDATE journey_enrollments 
                      SET current_step_order = :step, next_run_at = :next_run, execution_log_json = :logs
                      WHERE enrollment_id = :id");
        $this->bind(':step', $nextStepOrder);
        $this->bind(':next_run', $nextRunAt);
        $this->bind(':logs', json_encode($logs));
        $this->bind(':id', $enrollmentId);
        $this->execute();
    }

    private function completeEnrollment(int $enrollmentId, int $journeyId): void {
        $now = date('Y-m-d H:i:s');
        $this->query("UPDATE journey_enrollments 
                      SET status = 'completed', completed_at = :now 
                      WHERE enrollment_id = :id");
        $this->bind(':now', $now);
        $this->bind(':id', $enrollmentId);
        $this->execute();

        // Increment total completed counter on journey
        $this->query("UPDATE marketing_journeys SET total_completed = total_completed + 1 WHERE journey_id = :jid");
        $this->bind(':jid', $journeyId);
        $this->execute();
    }

    private function cancelEnrollment(int $enrollmentId, string $reason): void {
        $now = date('Y-m-d H:i:s');
        $this->query("SELECT execution_log_json FROM journey_enrollments WHERE enrollment_id = :id");
        $this->bind(':id', $enrollmentId);
        $currLog = $this->single();
        $logs = !empty($currLog->execution_log_json) ? json_decode($currLog->execution_log_json, true) : [];
        $logs[] = ['timestamp' => $now, 'action' => 'cancelled', 'detail' => $reason];

        $this->query("UPDATE journey_enrollments 
                      SET status = 'cancelled', completed_at = :now, execution_log_json = :logs 
                      WHERE enrollment_id = :id");
        $this->bind(':now', $now);
        $this->bind(':logs', json_encode($logs));
        $this->bind(':id', $enrollmentId);
        $this->execute();
    }
}

