<?php
/**
 * Raptor CRM - DB-Backed Queue Dispatcher & Deliverability Engine
 * 
 * Safe row locking (FOR UPDATE SKIP LOCKED), worker concurrency protection,
 * domain rate limiting, automated warm-up throttling, auto-pause safeguards,
 * exponential backoff retry, and RFC 8058 headers.
 */

class EmailQueueService {

    public const MAX_BOUNCE_RATE = 0.02;     // 2% bounce threshold
    public const MAX_COMPLAINT_RATE = 0.001; // 0.1% spam complaint threshold

    /**
     * Process a batch of pending queue items.
     * 
     * @param int $batchSize Number of items to process
     * @param int $rateLimitPerSec Throttling rate (emails per second)
     * @param string|null $workerId Unique ID of worker process
     * @return array [processed => int, sent => int, failed => int, suppressed => int, throttled => int]
     */
    public static function processBatch(int $batchSize = 50, int $rateLimitPerSec = 5, ?string $workerId = null): array {
        $worker = $workerId ?: ('worker_' . getmypid() . '_' . bin2hex(random_bytes(3)));
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        // 1. Safe Row Locking to prevent double sends across concurrent workers
        $lockedItemIds = self::lockPendingQueueItems($batchSize, $worker);
        if (empty($lockedItemIds)) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'suppressed' => 0, 'throttled' => 0];
        }

        // 2. Fetch locked items with full campaign, recipient, and contact data
        $inClause = implode(',', array_map('intval', $lockedItemIds));
        $sql = "SELECT q.*, 
                       r.email as recipient_email, 
                       r.subject_variant, 
                       r.unsubscribe_token,
                       r.status as recipient_status,
                       c.campaign_id,
                       c.name as campaign_name,
                       c.subject_a,
                       c.subject_b,
                       c.preheader,
                       c.from_name,
                       c.from_email,
                       c.reply_to,
                       c.html_content,
                       c.plain_text_content,
                       c.track_opens,
                       c.track_clicks,
                       c.status as campaign_status,
                       c.bounced_count,
                       c.complained_count,
                       c.sent_count,
                       cnt.first_name,
                       cnt.last_name,
                       cnt.company_name,
                       cnt.custom_fields
                FROM campaign_email_queue q
                JOIN campaign_recipients r ON q.recipient_id = r.recipient_id
                JOIN campaigns_broadcast c ON q.campaign_id = c.campaign_id
                LEFT JOIN campaign_contacts cnt ON r.contact_id = cnt.contact_id
                WHERE q.queue_id IN ({$inClause})
                ORDER BY q.priority ASC, q.scheduled_at ASC";

        $items = $db->query($sql)->fetchAll(PDO::FETCH_OBJ);

        $stats = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'suppressed' => 0,
            'throttled' => 0
        ];

        $mailer = MailerFactory::getAdapter();
        $suppressionModel = new CampaignSuppression();
        $delayMicroseconds = $rateLimitPerSec > 0 ? (int)(1000000 / $rateLimitPerSec) : 0;

        foreach ($items as $item) {
            $stats['processed']++;

            // -----------------------------------------------------------------
            // Check A: Campaign Status & Auto-Pause Safeguards
            // -----------------------------------------------------------------
            if ($item->campaign_status === CampaignBroadcast::STATUS_PAUSED || $item->campaign_status === CampaignBroadcast::STATUS_CANCELLED) {
                self::unlockQueueItem((int)$item->queue_id);
                continue;
            }

            // Evaluate live deliverability thresholds (> 2% bounce or > 0.1% complaint)
            if (self::shouldAutoPauseCampaign($item)) {
                self::autoPauseCampaign((int)$item->campaign_id, 'Deliverability safeguard: Bounce/Complaint threshold breached during sending.');
                self::unlockQueueItem((int)$item->queue_id);
                continue;
            }

            // -----------------------------------------------------------------
            // Check B: Idempotency Verification
            // -----------------------------------------------------------------
            if ($item->recipient_status === 'sent' || $item->recipient_status === 'delivered') {
                // Already sent by another thread or previously; mark queue item complete
                $db->prepare("UPDATE campaign_email_queue SET status = 'sent', locked_at = NULL WHERE queue_id = :qid")
                   ->execute([':qid' => $item->queue_id]);
                continue;
            }

            // -----------------------------------------------------------------
            // Check C: Global Suppression Firewall (Checked immediately before send)
            // -----------------------------------------------------------------
            $cleanEmail = strtolower(trim($item->recipient_email));
            if ($suppressionModel->isSuppressed($cleanEmail)) {
                $stats['suppressed']++;
                $db->prepare("UPDATE campaign_email_queue SET status = 'cancelled', last_error = 'Globally Suppressed', locked_at = NULL WHERE queue_id = :qid")
                   ->execute([':qid' => $item->queue_id]);
                $db->prepare("UPDATE campaign_recipients SET status = 'failed' WHERE recipient_id = :rid")
                   ->execute([':rid' => $item->recipient_id]);
                continue;
            }

            // -----------------------------------------------------------------
            // Check D: Domain Warm-Up Daily Cap
            // -----------------------------------------------------------------
            $fromDomain = substr(strrchr($item->from_email, "@"), 1);
            if (!WarmupScheduleService::canSend($fromDomain)) {
                $stats['throttled']++;
                // Reschedule for next day 09:00 UTC
                $tomorrow = date('Y-m-d 09:00:00', strtotime('+1 day'));
                $db->prepare("UPDATE campaign_email_queue SET status = 'pending', scheduled_at = :next, locked_at = NULL WHERE queue_id = :qid")
                   ->execute([':next' => $tomorrow, ':qid' => $item->queue_id]);
                continue;
            }

            // -----------------------------------------------------------------
            // 3. Render Content & Personalize Merge Tags
            // -----------------------------------------------------------------
            $subject = ($item->subject_variant === 'B' && !empty($item->subject_b)) ? $item->subject_b : $item->subject_a;

            $unsubToken = $item->unsubscribe_token ?: UnsubscribeService::generateToken((int)$item->campaign_id, (int)$item->recipient_id, $cleanEmail);
            $unsubUrl = UnsubscribeService::buildUnsubscribeUrl($unsubToken, (int)$item->campaign_id);
            $oneClickUrl = UnsubscribeService::buildOneClickUrl($unsubToken);

            $contactData = [
                'first_name' => $item->first_name,
                'last_name' => $item->last_name,
                'company_name' => $item->company_name,
                'email' => $cleanEmail,
                'custom_fields' => $item->custom_fields
            ];

            $context = [
                'unsubscribe_url' => $unsubUrl,
                'company_address' => 'Axiora Global Solutions, Cyber City, Hyderabad, India'
            ];

            $renderedHtml = CampaignTemplate::renderMergeTags($item->html_content, $contactData, $context);
            $renderedPlain = CampaignTemplate::renderMergeTags($item->plain_text_content, $contactData, $context);
            $renderedSubject = CampaignTemplate::renderMergeTags($subject, $contactData, $context);

            // Inject open tracking pixel and click tracking links if enabled
            $trackOpens = isset($item->track_opens) ? (bool)$item->track_opens : true;
            $trackClicks = isset($item->track_clicks) ? (bool)$item->track_clicks : true;
            if ($trackOpens || $trackClicks) {
                $renderedHtml = TrackingService::injectTracking($renderedHtml, $unsubToken, $trackOpens, $trackClicks);
            }

            // Construct RFC 8058 & anti-spam deliverability headers
            $headers = [
                'List-Unsubscribe' => "<mailto:unsub@{$fromDomain}?subject=unsubscribe-{$unsubToken}>, <{$oneClickUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                'Feedback-ID' => "camp_{$item->campaign_id}:raptor:{$fromDomain}",
                'Precedence' => 'bulk',
                'X-Campaign-ID' => (string)$item->campaign_id,
                'X-Recipient-ID' => (string)$item->recipient_id
            ];

            $messagePayload = [
                'to' => $cleanEmail,
                'to_name' => trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')),
                'from_email' => $item->from_email,
                'from_name' => $item->from_name,
                'reply_to' => $item->reply_to,
                'subject' => $renderedSubject,
                'html' => $renderedHtml,
                'text' => $renderedPlain,
                'headers' => $headers
            ];

            // -----------------------------------------------------------------
            // 4. Dispatch through Provider Adapter
            // -----------------------------------------------------------------
            $result = $mailer->send($messagePayload);

            if ($result->isSuccess()) {
                $stats['sent']++;
                WarmupScheduleService::recordSend($fromDomain);

                // Update queue and recipient records
                $db->prepare("UPDATE campaign_email_queue SET status = 'sent', locked_at = NULL, locked_by = NULL WHERE queue_id = :qid")
                   ->execute([':qid' => $item->queue_id]);

                $db->prepare("UPDATE campaign_recipients 
                              SET status = 'sent', sent_at = {$nowSql} 
                              WHERE recipient_id = :rid")
                   ->execute([':rid' => $item->recipient_id]);

                // Update campaign counters
                $db->prepare("UPDATE campaigns_broadcast 
                              SET sent_count = sent_count + 1, status = IF(status = 'queued', 'sending', status) 
                              WHERE campaign_id = :cid")
                   ->execute([':cid' => $item->campaign_id]);

                // Log event
                self::logQueueEvent((int)$item->campaign_id, (int)$item->recipient_id, 'sent', [
                    'provider_message_id' => $result->getMessageId(),
                    'provider' => $mailer->getName()
                ]);
            } else {
                $stats['failed']++;
                $errorMsg = $result->getErrorMessage();
                $isTransient = $result->isTransient();
                $newAttempts = (int)$item->attempts + 1;

                if ($isTransient && $newAttempts < (int)$item->max_attempts) {
                    // Exponential backoff: attempt 1 -> 5m, attempt 2 -> 30m, attempt 3 -> 2h
                    $delayMinutes = match ($newAttempts) {
                        1 => 5,
                        2 => 30,
                        default => 120
                    };
                    $nextTry = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));

                    $db->prepare("UPDATE campaign_email_queue 
                                  SET status = 'pending', attempts = :att, scheduled_at = :next, last_error = :err, locked_at = NULL 
                                  WHERE queue_id = :qid")
                       ->execute([':att' => $newAttempts, ':next' => $nextTry, ':err' => $errorMsg, ':qid' => $item->queue_id]);
                } else {
                    // Permanent 5xx failure or max attempts exhausted
                    $db->prepare("UPDATE campaign_email_queue 
                                  SET status = 'failed', attempts = :att, last_error = :err, locked_at = NULL 
                                  WHERE queue_id = :qid")
                       ->execute([':att' => $newAttempts, ':err' => $errorMsg, ':qid' => $item->queue_id]);

                    $db->prepare("UPDATE campaign_recipients 
                                  SET status = 'failed', bounce_reason = :err 
                                  WHERE recipient_id = :rid")
                       ->execute([':err' => $errorMsg, ':rid' => $item->recipient_id]);
                }
            }

            // Domain-level rate pacing
            if ($delayMicroseconds > 0) {
                usleep($delayMicroseconds);
            }
        }

        // Finalize completed campaigns if queue is now empty
        self::checkAndMarkCompletedCampaigns();

        return $stats;
    }

    /**
     * Lock batch of pending items with worker ID.
     */
    private static function lockPendingQueueItems(int $limit, string $worker): array {
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        // In MySQL 8+: use SELECT ... FOR UPDATE SKIP LOCKED
        // In SQLite / fallback: atomic update then select locked by worker
        if ($driver === 'mysql') {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT queue_id FROM campaign_email_queue 
                                      WHERE status = 'pending' AND scheduled_at <= NOW() 
                                      ORDER BY priority ASC, scheduled_at ASC 
                                      LIMIT :lim FOR UPDATE SKIP LOCKED");
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($rows)) {
                    $in = implode(',', array_map('intval', $rows));
                    $db->exec("UPDATE campaign_email_queue SET status = 'processing', locked_at = NOW(), locked_by = '{$worker}' WHERE queue_id IN ({$in})");
                }
                $db->commit();
                return $rows ?: [];
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            }
        }

        // Fallback atomic locking
        $up = $db->prepare("UPDATE campaign_email_queue 
                            SET status = 'processing', locked_at = {$nowSql}, locked_by = :worker 
                            WHERE queue_id IN (
                                SELECT queue_id FROM campaign_email_queue 
                                WHERE status = 'pending' AND scheduled_at <= {$nowSql} 
                                ORDER BY priority ASC, scheduled_at ASC 
                                LIMIT :lim
                            )");
        $up->bindValue(':worker', $worker, PDO::PARAM_STR);
        $up->bindValue(':lim', $limit, PDO::PARAM_INT);
        $up->execute();

        $sel = $db->prepare("SELECT queue_id FROM campaign_email_queue WHERE locked_by = :worker AND status = 'processing'");
        $sel->execute([':worker' => $worker]);
        return $sel->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function unlockQueueItem(int $queueId): void {
        $db = Database::getInstance()->getConnection();
        $db->prepare("UPDATE campaign_email_queue SET status = 'pending', locked_at = NULL, locked_by = NULL WHERE queue_id = :id")
           ->execute([':id' => $queueId]);
    }

    private static function shouldAutoPauseCampaign(object $item): bool {
        $sent = (int)$item->sent_count;
        if ($sent < 20) {
            return false; // Require minimum sample size before evaluating ratios
        }

        $bounceRate = (int)$item->bounced_count / $sent;
        $complaintRate = (int)$item->complained_count / $sent;

        return ($bounceRate > self::MAX_BOUNCE_RATE || $complaintRate > self::MAX_COMPLAINT_RATE);
    }

    public static function autoPauseCampaign(int $campaignId, string $reason): void {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE campaigns_broadcast 
                              SET status = 'paused', paused_reason = :reason 
                              WHERE campaign_id = :id");
        $stmt->execute([':reason' => $reason, ':id' => $campaignId]);

        if (class_exists('CampaignAudit')) {
            CampaignAudit::log(null, 'campaign.auto_paused', 'campaign', $campaignId, [
                'reason' => $reason
            ]);
        }
    }

    private static function checkAndMarkCompletedCampaigns(): void {
        $db = Database::getInstance()->getConnection();
        $sql = "UPDATE campaigns_broadcast c
                SET status = 'completed', completed_at = CURRENT_TIMESTAMP
                WHERE c.status IN ('queued', 'sending')
                  AND NOT EXISTS (
                      SELECT 1 FROM campaign_email_queue q 
                      WHERE q.campaign_id = c.campaign_id AND q.status IN ('pending', 'processing')
                  )";
        try {
            $db->exec($sql);
        } catch (Throwable $e) {}
    }

    private static function logQueueEvent(int $campId, int $recipId, string $eventType, array $payload): void {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO campaign_email_events (campaign_id, recipient_id, event_type, payload_json) 
                                  VALUES (:cid, :rid, :type, :payload)");
            $stmt->execute([
                ':cid' => $campId,
                ':rid' => $recipId,
                ':type' => $eventType,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Throwable $e) {}
    }
}

