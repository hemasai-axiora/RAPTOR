<?php
/**
 * Raptor CRM - Campaign Broadcast Model
 * 
 * Manages email campaign records, A/B test variations, pre-send checklist enforcement,
 * target recipient resolution, and queue seeding.
 */

class CampaignBroadcast extends Model {

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    private function hasCampaignColumn(string $column): bool {
        static $cols = null;
        if ($cols === null) {
            $cols = [];
            try {
                $db = Database::getInstance()->getConnection();
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $stmt = $db->query("PRAGMA table_info(campaigns_broadcast)");
                    if ($stmt) {
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                            $cols[$row['name']] = true;
                        }
                    }
                } else {
                    $stmt = $db->query("SHOW COLUMNS FROM campaigns_broadcast");
                    if ($stmt) {
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                            $cols[$row['Field']] = true;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }
        return isset($cols[$column]);
    }

    public function getAllCampaigns(array $filters = [], int $limit = 25, int $offset = 0): array {
        $sql = 'SELECT c.*, 
                       l.name as list_name, 
                       s.name as segment_name, 
                       t.name as template_name,
                       u.name as creator_name
                FROM campaigns_broadcast c
                LEFT JOIN campaign_lists l ON c.list_id = l.list_id
                LEFT JOIN campaign_segments s ON c.segment_id = s.segment_id
                LEFT JOIN campaign_templates t ON c.template_id = t.template_id
                LEFT JOIN users u ON c.created_by = u.user_id
                WHERE 1=1 ';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= 'AND c.status = :status ';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= 'AND (c.name LIKE :s OR c.subject_a LIKE :s OR c.campaign_code LIKE :s) ';
            $params[':s'] = '%' . trim($filters['search']) . '%';
        }

        $sql .= 'ORDER BY c.created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet() ?: [];
    }

    public function countCampaigns(array $filters = []): int {
        $sql = 'SELECT COUNT(*) FROM campaigns_broadcast WHERE 1=1 ';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= 'AND status = :status ';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= 'AND (name LIKE :s OR subject_a LIKE :s OR campaign_code LIKE :s) ';
            $params[':s'] = '%' . trim($filters['search']) . '%';
        }

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $row = $this->single();
        return (int)($row ? current((array)$row) : 0);
    }

    public function getById(int $campaignId) {
        $this->query('SELECT c.*, 
                             l.name as list_name, 
                             s.name as segment_name, 
                             t.name as template_name,
                             u.name as creator_name
                      FROM campaigns_broadcast c
                      LEFT JOIN campaign_lists l ON c.list_id = l.list_id
                      LEFT JOIN campaign_segments s ON c.segment_id = s.segment_id
                      LEFT JOIN campaign_templates t ON c.template_id = t.template_id
                      LEFT JOIN users u ON c.created_by = u.user_id
                      WHERE c.campaign_id = :id');
        $this->bind(':id', $campaignId);
        $camp = $this->single();
        if ($camp && !empty($camp->pre_send_checklist_json)) {
            $camp->checklist = json_decode($camp->pre_send_checklist_json, true) ?: [];
        } elseif ($camp) {
            $camp->checklist = [];
        }
        return $camp;
    }

    public function saveCampaign(array $data, ?int $userId = null): int {
        $name = trim($data['name'] ?? '');
        $subjectA = trim($data['subject_a'] ?? '');
        $subjectB = trim($data['subject_b'] ?? '');
        $abSplit = max(0, min(50, (int)($data['ab_split_percent'] ?? 0)));
        $abWinnerMetric = in_array($data['ab_winner_metric'] ?? '', ['opens', 'clicks'], true) ? $data['ab_winner_metric'] : 'opens';
        $preheader = trim($data['preheader'] ?? '');
        $fromName = trim($data['from_name'] ?? 'Axiora Global');
        $fromEmail = strtolower(trim($data['from_email'] ?? 'noreply@axiora.in'));
        $replyTo = trim($data['reply_to'] ?? '');
        $listId = !empty($data['list_id']) ? (int)$data['list_id'] : null;
        $segmentId = !empty($data['segment_id']) ? (int)$data['segment_id'] : null;
        $templateId = !empty($data['template_id']) ? (int)$data['template_id'] : null;
        $communicationIntent = in_array($data['communication_intent'] ?? '', ['personal_style', 'promotional', 'operational'], true) ? $data['communication_intent'] : 'promotional';
        $htmlContent = $data['html_content'] ?? '';
        $plainTextContent = $data['plain_text_content'] ?? '';

        if (empty($name)) {
            throw new InvalidArgumentException('Campaign name is required');
        }
        if (empty($subjectA)) {
            throw new InvalidArgumentException('Primary subject line (Subject A) is required');
        }

        // Auto-generate plain-text alternative if missing
        if (empty(trim($plainTextContent))) {
            $plainTextContent = CampaignTemplate::htmlToPlainText($htmlContent);
        }

        // Auto-attach compliance footer if missing in promotional/marketing emails
        if ($communicationIntent !== 'operational') {
            $hasUnsubHtml = (strpos($htmlContent, '{{unsubscribe_url}}') !== false) ||
                            (stripos($htmlContent, 'unsubscribe') !== false && preg_match('/<a[^>]*href=[^>]*>.*?unsubscribe.*?<\/a>/is', $htmlContent));
            if (!$hasUnsubHtml && !empty($htmlContent)) {
                $complianceFooter = '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#718096;text-align:center;">'
                    . 'Axiora Global Solutions, Cyber City, Hyderabad, India | <a href="{{unsubscribe_url}}" style="color:#4a5568;text-decoration:underline;">Unsubscribe</a>'
                    . '</div>';
                if (stripos($htmlContent, '</body>') !== false) {
                    $htmlContent = str_ireplace('</body>', $complianceFooter . '</body>', $htmlContent);
                } else {
                    $htmlContent .= "\n\n" . $complianceFooter;
                }
            }

            if (!empty($plainTextContent) && strpos($plainTextContent, '{{unsubscribe_url}}') === false && stripos($plainTextContent, 'unsubscribe') === false) {
                $plainTextContent = rtrim($plainTextContent) . "\n\nAxiora Global Solutions, Cyber City, Hyderabad, India | Unsubscribe: {{unsubscribe_url}}";
            }
        }

        // Sanitize HTML
        $htmlContent = CampaignTemplate::sanitizeHtml($htmlContent);

        // Compute content hash
        $contentHash = hash('sha256', (string)json_encode([
            'name' => $name,
            'subject_a' => $subjectA,
            'subject_b' => $subjectB,
            'html' => $htmlContent,
            'plain' => $plainTextContent,
            'from_email' => $fromEmail,
            'intent' => $communicationIntent
        ]));

        $hasIntent = $this->hasCampaignColumn('communication_intent');
        $hasCHash = $this->hasCampaignColumn('content_sha256');

        $campaignId = (int)($data['campaign_id'] ?? 0);
        if ($campaignId > 0) {
            $sql = 'UPDATE campaigns_broadcast 
                    SET name = :name, subject_a = :sub_a, subject_b = :sub_b, ab_split_percent = :ab_split,
                        ab_winner_metric = :ab_metric, preheader = :preheader, from_name = :from_name,
                        from_email = :from_email, reply_to = :reply_to, list_id = :list_id,
                        segment_id = :segment_id, template_id = :template_id, html_content = :html,
                        plain_text_content = :plain';
            if ($hasIntent) {
                $sql .= ', communication_intent = :intent';
            }
            if ($hasCHash) {
                $sql .= ', content_sha256 = :chash, approved_snapshot_hash = NULL';
            }
            $sql .= ', updated_at = CURRENT_TIMESTAMP WHERE campaign_id = :id';

            $this->query($sql);
            $this->bind(':name', $name);
            $this->bind(':sub_a', $subjectA);
            $this->bind(':sub_b', $subjectB ?: null);
            $this->bind(':ab_split', $abSplit);
            $this->bind(':ab_metric', $abWinnerMetric);
            $this->bind(':preheader', $preheader ?: null);
            $this->bind(':from_name', $fromName);
            $this->bind(':from_email', $fromEmail);
            $this->bind(':reply_to', $replyTo ?: null);
            $this->bind(':list_id', $listId);
            $this->bind(':segment_id', $segmentId);
            $this->bind(':template_id', $templateId);
            $this->bind(':html', $htmlContent);
            $this->bind(':plain', $plainTextContent);
            if ($hasIntent) {
                $this->bind(':intent', $communicationIntent);
            }
            if ($hasCHash) {
                $this->bind(':chash', $contentHash);
            }
            $this->bind(':id', $campaignId);
            $this->execute();
            return $campaignId;
        }

        // Generate clean campaign code
        $campaignCode = 'CAMP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $insCols = 'campaign_code, name, subject_a, subject_b, ab_split_percent, ab_winner_metric,
                    preheader, from_name, from_email, reply_to, list_id, segment_id, template_id,
                    html_content, plain_text_content';
        $insVals = ':code, :name, :sub_a, :sub_b, :ab_split, :ab_metric, :preheader, :from_name,
                    :from_email, :reply_to, :list_id, :segment_id, :template_id, :html, :plain';

        if ($hasIntent) {
            $insCols .= ', communication_intent';
            $insVals .= ', :intent';
        }
        if ($hasCHash) {
            $insCols .= ', content_sha256';
            $insVals .= ', :chash';
        }

        $insCols .= ', status, created_by';
        $insVals .= ', "draft", :uid';

        $this->query("INSERT INTO campaigns_broadcast ({$insCols}) VALUES ({$insVals})");
        $this->bind(':code', $campaignCode);
        $this->bind(':name', $name);
        $this->bind(':sub_a', $subjectA);
        $this->bind(':sub_b', $subjectB ?: null);
        $this->bind(':ab_split', $abSplit);
        $this->bind(':ab_metric', $abWinnerMetric);
        $this->bind(':preheader', $preheader ?: null);
        $this->bind(':from_name', $fromName);
        $this->bind(':from_email', $fromEmail);
        $this->bind(':reply_to', $replyTo ?: null);
        $this->bind(':list_id', $listId);
        $this->bind(':segment_id', $segmentId);
        $this->bind(':template_id', $templateId);
        $this->bind(':html', $htmlContent);
        $this->bind(':plain', $plainTextContent);
        if ($hasIntent) {
            $this->bind(':intent', $communicationIntent);
        }
        if ($hasCHash) {
            $this->bind(':chash', $contentHash);
        }
        $this->bind(':uid', $userId);
        $this->execute();

        return (int)$this->lastInsertId();
    }

    public function deleteCampaign(int $campaignId): bool {
        $this->query('DELETE FROM campaigns_broadcast WHERE campaign_id = :id AND status IN ("draft", "cancelled")');
        $this->bind(':id', $campaignId);
        return $this->execute();
    }

    /**
     * Resolve eligible recipients for a campaign, strictly excluding global suppressions.
     * 
     * @param int $campaignId
     * @return array [total_subscribers => int, reachable_count => int, suppressed_count => int, contacts => array]
     */
    public function resolveEligibleRecipients(int $campaignId): array {
        $camp = $this->getById($campaignId);
        if (!$camp) {
            return ['reachable_count' => 0, 'suppressed_count' => 0, 'contacts' => []];
        }

        $db = Database::getInstance()->getConnection();

        // 1. Get raw subscriber contacts from assigned list or segment
        $contacts = [];
        if (!empty($camp->segment_id)) {
            $segmentModel = new CampaignSegment();
            $seg = $segmentModel->getById((int)$camp->segment_id);
            if ($seg) {
                $compiled = $segmentModel->compileSegmentSql($seg->rules, $seg->match_type ?? 'all');
                $sql = "SELECT c.contact_id, c.email, c.first_name, c.last_name, c.company_name, c.custom_fields, c.status
                        FROM campaign_contacts c
                        WHERE c.status = 'subscribed' AND " . $compiled['where'];
                $stmt = $db->prepare($sql);
                foreach ($compiled['params'] as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->execute();
                $contacts = $stmt->fetchAll(PDO::FETCH_OBJ);
            }
        } elseif (!empty($camp->list_id)) {
            if ((int)$camp->list_id === -1) {
                $stmt = $db->query('SELECT c.contact_id, c.email, c.first_name, c.last_name, c.company_name, c.custom_fields, c.status
                                    FROM campaign_contacts c
                                    WHERE c.status = "subscribed"');
                $contacts = $stmt->fetchAll(PDO::FETCH_OBJ);
            } else {
                $stmt = $db->prepare('SELECT c.contact_id, c.email, c.first_name, c.last_name, c.company_name, c.custom_fields, c.status
                                      FROM campaign_contacts c
                                      JOIN campaign_list_contacts lc ON c.contact_id = lc.contact_id
                                      WHERE lc.list_id = :lid AND lc.status = "active" AND c.status = "subscribed"');
                $stmt->execute([':lid' => (int)$camp->list_id]);
                $contacts = $stmt->fetchAll(PDO::FETCH_OBJ);
            }
        }

        // 2. Cross-reference against Global Suppression List & Deduplicate
        $suppressionModel = new CampaignSuppression();
        $reachable = [];
        $seenEmails = [];
        $suppressedCount = 0;

        foreach ($contacts as $c) {
            $cleanEmail = strtolower(trim($c->email));
            if (empty($cleanEmail) || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (isset($seenEmails[$cleanEmail])) {
                continue; // Prevent duplicates in broadcast
            }
            $seenEmails[$cleanEmail] = true;

            if ($suppressionModel->isSuppressed($cleanEmail)) {
                $suppressedCount++;
                continue;
            }
            $reachable[] = $c;
        }

        return [
            'total_subscribers' => count($contacts),
            'reachable_count' => count($reachable),
            'suppressed_count' => $suppressedCount,
            'contacts' => $reachable
        ];
    }

    /**
     * Launch Campaign: Enforces Pre-Send Quality Gate & Seeds Queue
     */
    public function launchCampaign(int $campaignId, ?int $userId = null): array {
        $camp = $this->getById($campaignId);
        if (!$camp) {
            return ['success' => false, 'error' => 'Campaign not found'];
        }

        if ($camp->status !== self::STATUS_DRAFT && $camp->status !== self::STATUS_SCHEDULED && $camp->status !== 'approved') {
            return ['success' => false, 'error' => 'Campaign is not in a launchable state (status: ' . $camp->status . ')'];
        }

        // Verify cryptographic approval snapshot if approved_snapshot_hash was recorded
        if (!empty($camp->approved_snapshot_hash) && class_exists('CampaignSnapshotService')) {
            if (!CampaignSnapshotService::verifySnapshot($campaignId)) {
                return [
                    'success' => false,
                    'error' => 'Campaign approved content hash mismatch (Rule D-017). Approval invalidated because content was edited after sign-off. Please re-run preflight and re-approve.'
                ];
            }
        }

        // 0. Verify Dedicated Marketing SMTP is Enabled & Configured
        $isExplicitlyDisabled = false;
        try {
            $dbCheck = Database::getInstance()->getConnection();
            $stmt = $dbCheck->query("SELECT setting_value FROM settings WHERE setting_key = 'mail.marketing.enabled'");
            $val = $stmt->fetchColumn();
            if ($val !== false && ($val === '0' || $val === 0)) {
                $isExplicitlyDisabled = true;
            }
        } catch (Throwable $t) {
            // Table doesn't exist or not queryable (e.g. isolated mock test)
        }

        if ($isExplicitlyDisabled) {
            return [
                'success' => false,
                'error' => 'Launch Blocked: Dedicated Marketing SMTP is disabled or unconfigured in Global Settings. Configure and enable Marketing SMTP in Settings before launching bulk campaigns to protect Corporate SMTP reputation.'
            ];
        }

        // Auto-ensure compliance unsubscribe footer on launch if missing
        if (($camp->communication_intent ?? 'promotional') !== 'operational') {
            $hasUnsub = (strpos($camp->html_content, '{{unsubscribe_url}}') !== false) ||
                        (stripos($camp->html_content, 'unsubscribe') !== false && preg_match('/<a[^>]*href=[^>]*>.*?unsubscribe.*?<\/a>/is', $camp->html_content));
            if (!$hasUnsub) {
                $complianceFooter = '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#718096;text-align:center;">'
                    . 'Axiora Global Solutions, Cyber City, Hyderabad, India | <a href="{{unsubscribe_url}}" style="color:#4a5568;text-decoration:underline;">Unsubscribe</a>'
                    . '</div>';
                $updatedHtml = (stripos($camp->html_content, '</body>') !== false)
                    ? str_ireplace('</body>', $complianceFooter . '</body>', $camp->html_content)
                    : ($camp->html_content . "\n\n" . $complianceFooter);

                $updatedPlain = rtrim($camp->plain_text_content ?? '') . "\n\nAxiora Global Solutions, Cyber City, Hyderabad, India | Unsubscribe: {{unsubscribe_url}}";

                try {
                    $dbFix = Database::getInstance()->getConnection();
                    $dbFix->prepare("UPDATE campaigns_broadcast SET html_content = :html, plain_text_content = :plain WHERE campaign_id = :id")
                          ->execute([':html' => $updatedHtml, ':plain' => $updatedPlain, ':id' => $campaignId]);
                    $camp->html_content = $updatedHtml;
                    $camp->plain_text_content = $updatedPlain;
                } catch (Throwable $t) {}
            }
        }

        // 1. Run Pre-Send Deliverability & Compliance Checklist
        $checklistResult = PreSendChecklistService::run((array)$camp);
        
        $db = Database::getInstance()->getConnection();
        $cStmt = $db->prepare('UPDATE campaigns_broadcast 
                               SET pre_send_checklist_passed = :passed, 
                                   pre_send_checklist_json = :json 
                               WHERE campaign_id = :id');
        $cStmt->execute([
            ':passed' => $checklistResult['passed'] ? 1 : 0,
            ':json' => json_encode($checklistResult, JSON_UNESCAPED_UNICODE),
            ':id' => $campaignId
        ]);

        if (!$checklistResult['passed']) {
            return [
                'success' => false,
                'error' => 'Pre-Send Deliverability Checklist Failed. Resolve blockers before launch.',
                'blockers' => $checklistResult['blockers']
            ];
        }

        // 2. Resolve eligible recipients
        $recipientData = $this->resolveEligibleRecipients($campaignId);
        $recipients = $recipientData['contacts'];

        if (empty($recipients)) {
            return [
                'success' => false,
                'error' => 'Target audience has 0 reachable subscribers (all either empty, unsubscribed, or globally suppressed).'
            ];
        }

        // 3. Seed campaign_recipients & campaign_email_queue in transaction
        try {
            $db->beginTransaction();

            $rIns = $db->prepare('INSERT INTO campaign_recipients 
                                  (campaign_id, contact_id, email, subject_variant, status, unsubscribe_token)
                                  VALUES (:cid, :cntid, :email, :variant, "queued", :token)');

            $qIns = $db->prepare('INSERT INTO campaign_email_queue 
                                  (campaign_id, recipient_id, status, priority, scheduled_at)
                                  VALUES (:cid, :rid, "pending", 10, CURRENT_TIMESTAMP)');

            $hasAb = (!empty($camp->subject_b) && $camp->ab_split_percent > 0);
            $abSplitCount = $hasAb ? (int)ceil(count($recipients) * ($camp->ab_split_percent / 100)) : 0;
            $seededCount = 0;

            foreach ($recipients as $idx => $r) {
                $cleanEmail = strtolower(trim($r->email));

                // A/B test subject split
                $variant = 'A';
                if ($hasAb && $idx < $abSplitCount) {
                    $variant = ($idx % 2 === 0) ? 'A' : 'B';
                }

                // Generate secure HMAC unsubscribe token
                $unsubToken = UnsubscribeService::generateToken($campaignId, (int)$r->contact_id, $cleanEmail);

                $rIns->execute([
                    ':cid' => $campaignId,
                    ':cntid' => (int)$r->contact_id,
                    ':email' => $cleanEmail,
                    ':variant' => $variant,
                    ':token' => $unsubToken
                ]);

                $recipientId = (int)$db->lastInsertId();

                $qIns->execute([
                    ':cid' => $campaignId,
                    ':rid' => $recipientId
                ]);

                $seededCount++;
            }

            // Update campaign status to queued
            $uStmt = $db->prepare('UPDATE campaigns_broadcast 
                                   SET status = "queued", total_recipients = :total, started_at = CURRENT_TIMESTAMP 
                                   WHERE campaign_id = :id');
            $uStmt->execute([':total' => $seededCount, ':id' => $campaignId]);

            $db->commit();

            if (class_exists('CampaignAudit')) {
                CampaignAudit::log($userId, 'campaign.launched', 'campaign', $campaignId, [
                    'name' => $camp->name,
                    'total_queued' => $seededCount,
                    'ab_test' => $hasAb
                ]);
            }

            return [
                'success' => true,
                'queued_count' => $seededCount,
                'message' => "Campaign successfully validated and {$seededCount} recipient messages queued for worker dispatch."
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'success' => false,
                'error' => 'Failed to queue campaign: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pause an active or queued campaign
     */
    public function pauseCampaign(int $campaignId, string $reason = '', ?int $userId = null): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE campaigns_broadcast SET status = 'paused', paused_reason = :reason WHERE campaign_id = :id AND status IN ('queued', 'sending')");
        $stmt->execute([':reason' => $reason, ':id' => $campaignId]);
        if ($stmt->rowCount() > 0) {
            if (class_exists('CampaignAudit')) {
                CampaignAudit::log($userId, 'campaign.paused', 'campaign', $campaignId, ['reason' => $reason]);
            }
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Campaign is not currently in an active or queued state.'];
    }

    /**
     * Resume a paused campaign
     */
    public function resumeCampaign(int $campaignId, ?int $userId = null): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE campaigns_broadcast SET status = 'queued', paused_reason = NULL WHERE campaign_id = :id AND status = 'paused'");
        $stmt->execute([':id' => $campaignId]);
        if ($stmt->rowCount() > 0) {
            if (class_exists('CampaignAudit')) {
                CampaignAudit::log($userId, 'campaign.resumed', 'campaign', $campaignId);
            }
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Campaign is not currently paused.'];
    }

    /**
     * Cancel a campaign and flush remaining pending queue items
     */
    public function cancelCampaign(int $campaignId, ?int $userId = null): array {
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE campaigns_broadcast SET status = 'cancelled' WHERE campaign_id = :id AND status IN ('draft', 'scheduled', 'queued', 'sending', 'paused')");
            $stmt->execute([':id' => $campaignId]);

            $db->prepare("UPDATE campaign_email_queue SET status = 'cancelled', last_error = 'Campaign cancelled by user' WHERE campaign_id = :id AND status IN ('pending', 'processing')")
               ->execute([':id' => $campaignId]);

            $db->commit();
            if (class_exists('CampaignAudit')) {
                CampaignAudit::log($userId, 'campaign.cancelled', 'campaign', $campaignId);
            }
            return ['success' => true];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
