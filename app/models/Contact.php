<?php
/**
 * Contact Model — Unified Customer 360 Core Domain Entity
 * -------------------------------------------------------------------------
 * Serves as the single master identity for all prospects, leads, customers,
 * and email/WhatsApp subscribers in the Marketing Management Platform.
 *
 * Capabilities:
 * - Deterministic identity resolution across channels (email, phone, whatsapp, cookie)
 * - Omnichannel activity timeline aggregation
 * - Dynamic lifecycle stage & lead score progression
 * - Centralized cross-channel suppression management
 * - Strict segregation from payroll/compensation records
 */

class Contact extends Model {
    public const LIFECYCLE_STAGES = [
        'subscriber',
        'lead',
        'mql',
        'sql',
        'opportunity',
        'customer',
        'evangelist',
        'churned'
    ];

    public const CHANNELS = [
        'email',
        'phone',
        'whatsapp',
        'cookie',
        'meta_psid',
        'linkedin_id'
    ];

    public const ACTIVITY_CHANNELS = [
        'web',
        'form',
        'email',
        'whatsapp',
        'call',
        'sms',
        'social',
        'crm',
        'invoice'
    ];

    /**
     * Resolves an existing contact by any identity (email, phone, whatsapp, etc.)
     * or atomically creates a new unified profile if not found.
     */
    public function findOrCreateByIdentity(string $channel, string $identityValue, array $contactData = []): object {
        $channel = strtolower(trim($channel));
        $normalizedVal = $this->normalizeIdentity($channel, $identityValue);

        if (empty($normalizedVal)) {
            throw new InvalidArgumentException("Identity value cannot be empty for channel [{$channel}]");
        }

        // 1. Look up primary identity
        $this->query("SELECT contact_id FROM contact_identities WHERE channel = :channel AND identity_value = :val LIMIT 1");
        $this->bind(':channel', $channel);
        $this->bind(':val', $normalizedVal);
        $existing = $this->single();

        $contactId = $existing ? (int)$existing->contact_id : null;

        // 2. Fallback secondary identity check if secondary identity provided in $contactData
        if (!$contactId) {
            if ($channel !== 'email' && !empty($contactData['email'])) {
                $emailVal = $this->normalizeIdentity('email', $contactData['email']);
                $this->query("SELECT contact_id FROM contact_identities WHERE channel = 'email' AND identity_value = :val LIMIT 1");
                $this->bind(':val', $emailVal);
                $sec = $this->single();
                if ($sec) $contactId = (int)$sec->contact_id;
            } elseif ($channel !== 'phone' && !empty($contactData['phone'])) {
                $phoneVal = $this->normalizeIdentity('phone', $contactData['phone']);
                $this->query("SELECT contact_id FROM contact_identities WHERE channel = 'phone' AND identity_value = :val LIMIT 1");
                $this->bind(':val', $phoneVal);
                $sec = $this->single();
                if ($sec) $contactId = (int)$sec->contact_id;
            }
        }

        // 3. If contact exists, update any missing demographic attributes and link new identity
        if ($contactId) {
            $contact = $this->getById($contactId);
            $this->addIdentity($contactId, $channel, $normalizedVal, false);

            $updates = [];
            $params = [':id' => $contactId];

            if (empty($contact->first_name) && !empty($contactData['first_name'])) {
                $updates[] = "first_name = :fname";
                $params[':fname'] = trim($contactData['first_name']);
            }
            if (empty($contact->last_name) && !empty($contactData['last_name'])) {
                $updates[] = "last_name = :lname";
                $params[':lname'] = trim($contactData['last_name']);
            }
            if (empty($contact->company_name) && !empty($contactData['company_name'])) {
                $updates[] = "company_name = :cname";
                $params[':cname'] = trim($contactData['company_name']);
            }
            if (empty($contact->primary_email) && $channel === 'email') {
                $updates[] = "primary_email = :pemail";
                $params[':pemail'] = $normalizedVal;
            }
            if (empty($contact->primary_phone) && ($channel === 'phone' || $channel === 'whatsapp')) {
                $updates[] = "primary_phone = :pphone";
                $params[':pphone'] = $normalizedVal;
            }

            if (!empty($updates)) {
                $this->query("UPDATE contacts SET " . implode(', ', $updates) . " WHERE contact_id = :id");
                foreach ($params as $k => $v) {
                    $this->bind($k, $v);
                }
                $this->execute();
            }

            return $this->getById($contactId);
        }

        // 4. Create brand new unified profile
        $uuid = $this->generateUuid();
        $primaryEmail = ($channel === 'email') ? $normalizedVal : (!empty($contactData['email']) ? $this->normalizeIdentity('email', $contactData['email']) : null);
        $primaryPhone = ($channel === 'phone' || $channel === 'whatsapp') ? $normalizedVal : (!empty($contactData['phone']) ? $this->normalizeIdentity('phone', $contactData['phone']) : null);
        
        $stage = in_array($contactData['lifecycle_stage'] ?? '', self::LIFECYCLE_STAGES, true) ? $contactData['lifecycle_stage'] : 'lead';
        $quality = in_array($contactData['lead_quality'] ?? '', ['hot', 'warm', 'cold'], true) ? $contactData['lead_quality'] : 'warm';
        $score = isset($contactData['lead_score']) ? (int)$contactData['lead_score'] : 0;
        $assigned = !empty($contactData['assigned_user_id']) ? (int)$contactData['assigned_user_id'] : null;

        $customFields = !empty($contactData['custom_fields']) ? json_encode($contactData['custom_fields']) : null;

        $this->query("INSERT INTO contacts (
            contact_uuid, primary_email, primary_phone, first_name, last_name, company_name,
            lifecycle_stage, lead_score, lead_quality, assigned_user_id, custom_fields_json,
            source, utm_source, utm_medium, utm_campaign, created_at
        ) VALUES (
            :uuid, :pemail, :pphone, :fname, :lname, :cname,
            :stage, :score, :quality, :assigned, :custom,
            :source, :usrc, :umed, :ucmp, :now
        )");

        $this->bind(':uuid', $uuid);
        $this->bind(':pemail', $primaryEmail);
        $this->bind(':pphone', $primaryPhone);
        $this->bind(':fname', $contactData['first_name'] ?? null);
        $this->bind(':lname', $contactData['last_name'] ?? null);
        $this->bind(':cname', $contactData['company_name'] ?? null);
        $this->bind(':stage', $stage);
        $this->bind(':score', $score);
        $this->bind(':quality', $quality);
        $this->bind(':assigned', $assigned);
        $this->bind(':custom', $customFields);
        $this->bind(':source', $contactData['source'] ?? 'direct');
        $this->bind(':usrc', $contactData['utm_source'] ?? null);
        $this->bind(':umed', $contactData['utm_medium'] ?? null);
        $this->bind(':ucmp', $contactData['utm_campaign'] ?? null);
        $this->bind(':now', date('Y-m-d H:i:s'));

        $this->execute();
        $contactId = (int)$this->lastInsertId();

        // 5. Register primary identity
        $this->addIdentity($contactId, $channel, $normalizedVal, true);

        // Register complementary identities if present
        if ($channel !== 'email' && $primaryEmail) {
            $this->addIdentity($contactId, 'email', $primaryEmail, true);
        }
        if ($channel !== 'phone' && $primaryPhone) {
            $this->addIdentity($contactId, 'phone', $primaryPhone, true);
        }

        // 6. Log profile creation activity
        $this->recordActivity(
            $contactId,
            'crm',
            'contact_created',
            'Unified Contact Profile Initialized',
            "Profile created via channel [{$channel}] with initial lifecycle stage [{$stage}].",
            ['source' => $contactData['source'] ?? 'direct']
        );

        return $this->getById($contactId);
    }

    public function getById(int $contactId): ?object {
        $this->query("SELECT * FROM contacts WHERE contact_id = :id LIMIT 1");
        $this->bind(':id', $contactId);
        $row = $this->single();
        return $row ?: null;
    }

    public function getByUuid(string $uuid): ?object {
        $this->query("SELECT * FROM contacts WHERE contact_uuid = :uuid LIMIT 1");
        $this->bind(':uuid', trim($uuid));
        $row = $this->single();
        return $row ?: null;
    }

    public function getByEmail(string $email): ?object {
        $email = $this->normalizeIdentity('email', $email);
        $this->query("SELECT c.* FROM contacts c
                      JOIN contact_identities ci ON c.contact_id = ci.contact_id
                      WHERE ci.channel = 'email' AND ci.identity_value = :email
                      LIMIT 1");
        $this->bind(':email', $email);
        $row = $this->single();
        return $row ?: null;
    }

    public function getByPhone(string $phone): ?object {
        $phone = $this->normalizeIdentity('phone', $phone);
        $this->query("SELECT c.* FROM contacts c
                      JOIN contact_identities ci ON c.contact_id = ci.contact_id
                      WHERE ci.channel IN ('phone', 'whatsapp') AND ci.identity_value = :phone
                      LIMIT 1");
        $this->bind(':phone', $phone);
        $row = $this->single();
        return $row ?: null;
    }

    public function getIdentities(int $contactId): array {
        $this->query("SELECT * FROM contact_identities WHERE contact_id = :id ORDER BY is_primary DESC, identity_id ASC");
        $this->bind(':id', $contactId);
        return $this->resultSet() ?: [];
    }

    public function addIdentity(int $contactId, string $channel, string $identityValue, bool $isPrimary = false): bool {
        $channel = strtolower(trim($channel));
        $normalizedVal = $this->normalizeIdentity($channel, $identityValue);
        if (empty($normalizedVal)) return false;

        try {
            $this->query("INSERT INTO contact_identities (contact_id, channel, identity_value, is_primary, verified_at, created_at)
                          VALUES (:cid, :chan, :val, :prim, :ver, :now)");
            $this->bind(':cid', $contactId);
            $this->bind(':chan', $channel);
            $this->bind(':val', $normalizedVal);
            $this->bind(':prim', $isPrimary ? 1 : 0);
            $this->bind(':ver', date('Y-m-d H:i:s'));
            $this->bind(':now', date('Y-m-d H:i:s'));
            return $this->execute();
        } catch (Throwable $e) {
            // Likely duplicate entry, safely ignore
            return false;
        }
    }

    public function getActivities(int $contactId, int $limit = 50, int $offset = 0): array {
        $this->query("SELECT a.*, u.name AS actor_name
                      FROM contact_activities a
                      LEFT JOIN users u ON a.actor_user_id = u.user_id
                      WHERE a.contact_id = :cid
                      ORDER BY a.occurred_at DESC, a.activity_id DESC
                      LIMIT :lim OFFSET :off");
        $this->bind(':cid', $contactId);
        $this->bind(':lim', max(1, $limit));
        $this->bind(':off', max(0, $offset));
        return $this->resultSet() ?: [];
    }

    public function recordActivity(
        int $contactId,
        string $channel,
        string $type,
        string $title,
        ?string $description = null,
        ?array $payload = null,
        ?int $actorUserId = null,
        ?string $occurredAt = null
    ): int {
        $this->query("INSERT INTO contact_activities (
            contact_id, channel, activity_type, title, description, payload_json, actor_user_id, occurred_at
        ) VALUES (
            :cid, :chan, :type, :title, :desc, :pay, :actor, :at
        )");
        $this->bind(':cid', $contactId);
        $this->bind(':chan', in_array($channel, self::ACTIVITY_CHANNELS, true) ? $channel : 'crm');
        $this->bind(':type', substr(trim($type), 0, 80));
        $this->bind(':title', substr(trim($title), 0, 255));
        $this->bind(':desc', $description);
        $this->bind(':pay', $payload ? json_encode($payload) : null);
        $this->bind(':actor', $actorUserId);
        $this->bind(':at', $occurredAt ?: date('Y-m-d H:i:s'));

        if ($this->execute()) {
            return (int)$this->lastInsertId();
        }
        return 0;
    }

    public function setLifecycleStage(int $contactId, string $newStage, ?int $actorUserId = null): bool {
        if (!in_array($newStage, self::LIFECYCLE_STAGES, true)) {
            return false;
        }
        $contact = $this->getById($contactId);
        if (!$contact || $contact->lifecycle_stage === $newStage) {
            return true;
        }

        $oldStage = $contact->lifecycle_stage;
        $this->query("UPDATE contacts SET lifecycle_stage = :stage WHERE contact_id = :id");
        $this->bind(':stage', $newStage);
        $this->bind(':id', $contactId);
        $success = $this->execute();

        if ($success) {
            $this->recordActivity(
                $contactId,
                'crm',
                'lifecycle_stage_change',
                "Lifecycle Stage Changed: {$oldStage} -> {$newStage}",
                "Contact transitioned from stage [{$oldStage}] to [{$newStage}].",
                ['old_stage' => $oldStage, 'new_stage' => $newStage],
                $actorUserId
            );
        }

        return $success;
    }

    public function updateLeadScore(int $contactId, int $delta, string $reason, ?int $actorUserId = null): int {
        $contact = $this->getById($contactId);
        if (!$contact) return 0;

        $newScore = max(0, (int)$contact->lead_score + $delta);
        $newQuality = ($newScore >= 50) ? 'hot' : (($newScore >= 15) ? 'warm' : 'cold');

        $this->query("UPDATE contacts SET lead_score = :score, lead_quality = :quality WHERE contact_id = :id");
        $this->bind(':score', $newScore);
        $this->bind(':quality', $newQuality);
        $this->bind(':id', $contactId);
        $this->execute();

        $this->recordActivity(
            $contactId,
            'crm',
            'lead_score_adjusted',
            "Lead Score Adjusted: " . ($delta >= 0 ? "+{$delta}" : "{$delta}") . " (Score: {$newScore})",
            "Reason: {$reason}",
            ['delta' => $delta, 'score' => $newScore, 'quality' => $newQuality],
            $actorUserId
        );

        return $newScore;
    }

    public function suppressContact(int $contactId, string $reason = 'manual_optout', ?int $actorUserId = null): bool {
        $contact = $this->getById($contactId);
        if (!$contact) return false;

        $this->query("UPDATE contacts SET is_suppressed = 1, suppression_reason = :reason, suppressed_at = :at WHERE contact_id = :id");
        $this->bind(':reason', $reason);
        $this->bind(':at', date('Y-m-d H:i:s'));
        $this->bind(':id', $contactId);
        $success = $this->execute();

        if ($success) {
            // 1. Cross-bridge to campaign_suppression_list if primary_email exists
            if (!empty($contact->primary_email)) {
                try {
                    $this->query("INSERT INTO campaign_suppression_list (email, reason, source, created_at)
                                  VALUES (:email, :reason, 'customer_360_bridge', :at)");
                    $this->bind(':email', $contact->primary_email);
                    $this->bind(':reason', $reason);
                    $this->bind(':at', date('Y-m-d H:i:s'));
                    $this->execute();
                } catch (Throwable $e) {}
            }

            // 2. Cross-bridge to CRM leads: Mark status = 'unsubscribed' to protect sales reps
            if (!empty($contact->primary_email) || !empty($contact->primary_phone)) {
                $leadUpdate = "UPDATE leads SET status = 'unsubscribed' WHERE status != 'converted' AND (contact_id = :cid";
                if (!empty($contact->primary_email)) $leadUpdate .= " OR LOWER(email) = :lemail";
                if (!empty($contact->primary_phone)) $leadUpdate .= " OR phone = :lphone";
                $leadUpdate .= ")";
                
                $this->query($leadUpdate);
                $this->bind(':cid', $contactId);
                if (!empty($contact->primary_email)) $this->bind(':lemail', $contact->primary_email);
                if (!empty($contact->primary_phone)) $this->bind(':lphone', $contact->primary_phone);
                $this->execute();
            }

            // 3. Log activity
            $this->recordActivity(
                $contactId,
                'crm',
                'contact_suppressed',
                'Contact Suppressed Across All Marketing Channels',
                "Contact marked as suppressed due to [{$reason}]. Outbound campaigns halted.",
                ['reason' => $reason],
                $actorUserId
            );
        }

        return $success;
    }

    public function unsuppressContact(int $contactId, ?int $actorUserId = null): bool {
        $contact = $this->getById($contactId);
        if (!$contact) return false;

        $this->query("UPDATE contacts SET is_suppressed = 0, suppression_reason = NULL, suppressed_at = NULL WHERE contact_id = :id");
        $this->bind(':id', $contactId);
        $success = $this->execute();

        if ($success && !empty($contact->primary_email)) {
            $this->query("DELETE FROM campaign_suppression_list WHERE LOWER(email) = :email");
            $this->bind(':email', $contact->primary_email);
            $this->execute();

            $this->recordActivity(
                $contactId,
                'crm',
                'contact_unsuppressed',
                'Contact Suppression Lifted',
                'Contact unsuppressed. Communication permitted.',
                [],
                $actorUserId
            );
        }

        return $success;
    }

    public function getContacts(array $filters = [], int $limit = 50, int $offset = 0): array {
        [$whereSql, $params] = $this->buildFilterConditions($filters);

        $sql = "SELECT c.*, u.name AS assigned_user_name
                FROM contacts c
                LEFT JOIN users u ON c.assigned_user_id = u.user_id
                {$whereSql}
                ORDER BY c.updated_at DESC
                LIMIT :limit OFFSET :offset";

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $this->bind(':limit', max(1, $limit));
        $this->bind(':offset', max(0, $offset));

        return $this->resultSet() ?: [];
    }

    public function countContacts(array $filters = []): int {
        [$whereSql, $params] = $this->buildFilterConditions($filters);
        $this->query("SELECT COUNT(*) AS total FROM contacts c {$whereSql}");
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $row = $this->single();
        return $row ? (int)$row->total : 0;
    }

    private function buildFilterConditions(array $filters): array {
        $conds = [];
        $params = [];

        if (!empty($filters['lifecycle_stage'])) {
            $conds[] = "c.lifecycle_stage = :stage";
            $params[':stage'] = $filters['lifecycle_stage'];
        }
        if (!empty($filters['lead_quality'])) {
            $conds[] = "c.lead_quality = :quality";
            $params[':quality'] = $filters['lead_quality'];
        }
        if (isset($filters['is_suppressed']) && $filters['is_suppressed'] !== '') {
            $conds[] = "c.is_suppressed = :supp";
            $params[':supp'] = (int)$filters['is_suppressed'];
        }
        if (!empty($filters['assigned_user_id'])) {
            $conds[] = "c.assigned_user_id = :uid";
            $params[':uid'] = (int)$filters['assigned_user_id'];
        }
        if (!empty($filters['search'])) {
            $conds[] = "(c.primary_email LIKE :srch OR c.primary_phone LIKE :srch OR c.first_name LIKE :srch OR c.last_name LIKE :srch OR c.company_name LIKE :srch)";
            $params[':srch'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = !empty($conds) ? 'WHERE ' . implode(' AND ', $conds) : '';
        return [$whereSql, $params];
    }

    private function normalizeIdentity(string $channel, string $val): string {
        $val = trim($val);
        if ($channel === 'email') {
            return strtolower($val);
        }
        if ($channel === 'phone' || $channel === 'whatsapp') {
            // Strip non-digits except leading +
            $clean = preg_replace('/(?<!^)\+|[^\d+]/', '', $val);
            return $clean ?: '';
        }
        return $val;
    }

    private function generateUuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

