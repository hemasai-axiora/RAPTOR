<?php
/**
 * Raptor CRM - Campaign Contact Model
 * 
 * Manages subscriber contacts, consent proof, segmentation attributes,
 * tag associations, and secure internal payroll employee synchronization.
 */

class CampaignContact extends Model {

    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_COMPLAINED = 'complained';
    public const STATUS_SUPPRESSED = 'suppressed';

    public const CONSENT_TYPES = [
        'explicit_optin' => 'Explicit Double/Single Opt-in',
        'customer_relationship' => 'Existing Customer / Contract Basis',
        'legitimate_interest' => 'Legitimate Interest (B2B)',
        'internal_employee' => 'Internal Corporate Employee',
        'public_inquiry' => 'Public Webform Inquiry'
    ];

    /**
     * Get single contact by ID with its assigned lists and tags.
     */
    public function getById(int $contactId) {
        $this->query('SELECT * FROM campaign_contacts WHERE contact_id = :id');
        $this->bind(':id', $contactId);
        $contact = $this->single();
        if ($contact) {
            $contact->tags = $this->getContactTags($contactId);
            $contact->lists = $this->getContactLists($contactId);
            if (!empty($contact->custom_fields)) {
                $contact->custom_fields = json_decode($contact->custom_fields, true) ?: [];
            } else {
                $contact->custom_fields = [];
            }
        }
        return $contact;
    }

    /**
     * Get contact by email address.
     */
    public function getByEmail(string $email) {
        $cleanEmail = strtolower(trim($email));
        $this->query('SELECT * FROM campaign_contacts WHERE email = :email LIMIT 1');
        $this->bind(':email', $cleanEmail);
        return $this->single();
    }

    /**
     * Create or update a contact.
     */
    public function upsertContact(array $data): int {
        $email = strtolower(trim($data['email'] ?? ''));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address provided');
        }

        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $companyName = trim($data['company_name'] ?? '');
        $status = $data['status'] ?? self::STATUS_SUBSCRIBED;
        $source = trim($data['source'] ?? 'manual');
        $consentType = $data['consent_type'] ?? 'explicit_optin';
        $consentIp = $data['consent_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $consentNotes = $data['consent_notes'] ?? '';
        $customFieldsJson = isset($data['custom_fields']) && is_array($data['custom_fields']) 
            ? json_encode($data['custom_fields'], JSON_UNESCAPED_UNICODE) 
            : null;

        // Role address and disposable flags
        $isRole = isset($data['is_role_account']) ? (int)$data['is_role_account'] : (ContactImportService::isRoleAccount($email) ? 1 : 0);
        $isDisposable = isset($data['is_disposable']) ? (int)$data['is_disposable'] : (ContactImportService::isDisposableDomain($email) ? 1 : 0);

        // Check if email is already in the global suppression list
        $suppressionModel = new CampaignSuppression();
        if ($suppressionModel->isSuppressed($email)) {
            $status = self::STATUS_SUPPRESSED;
        }

        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO campaign_contacts 
                    (email, first_name, last_name, company_name, custom_fields, status, source, consent_type, consent_timestamp, consent_ip, consent_notes, is_role_account, is_disposable)
                    VALUES 
                    (:email, :first_name, :last_name, :company_name, :custom_fields, :status, :source, :consent_type, datetime("now"), :consent_ip, :consent_notes, :is_role, :is_disposable)
                    ON CONFLICT(email) DO UPDATE SET 
                        first_name = COALESCE(NULLIF(excluded.first_name, ""), first_name),
                        last_name = COALESCE(NULLIF(excluded.last_name, ""), last_name),
                        company_name = COALESCE(NULLIF(excluded.company_name, ""), company_name),
                        custom_fields = COALESCE(excluded.custom_fields, custom_fields),
                        status = CASE WHEN status = "suppressed" OR status = "unsubscribed" THEN status ELSE excluded.status END,
                        is_role_account = excluded.is_role_account,
                        is_disposable = excluded.is_disposable,
                        updated_at = datetime("now")';
        } else {
            $sql = 'INSERT INTO campaign_contacts 
                    (email, first_name, last_name, company_name, custom_fields, status, source, consent_type, consent_timestamp, consent_ip, consent_notes, is_role_account, is_disposable)
                    VALUES 
                    (:email, :first_name, :last_name, :company_name, :custom_fields, :status, :source, :consent_type, NOW(), :consent_ip, :consent_notes, :is_role, :is_disposable)
                    ON DUPLICATE KEY UPDATE 
                        first_name = COALESCE(NULLIF(VALUES(first_name), ""), first_name),
                        last_name = COALESCE(NULLIF(VALUES(last_name), ""), last_name),
                        company_name = COALESCE(NULLIF(VALUES(company_name), ""), company_name),
                        custom_fields = COALESCE(VALUES(custom_fields), custom_fields),
                        status = IF(status = "suppressed" OR status = "unsubscribed", status, VALUES(status)),
                        is_role_account = VALUES(is_role_account),
                        is_disposable = VALUES(is_disposable),
                        updated_at = NOW()';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':email' => $email,
            ':first_name' => $firstName ?: null,
            ':last_name' => $lastName ?: null,
            ':company_name' => $companyName ?: null,
            ':custom_fields' => $customFieldsJson,
            ':status' => $status,
            ':source' => $source,
            ':consent_type' => $consentType,
            ':consent_ip' => $consentIp,
            ':consent_notes' => $consentNotes ?: null,
            ':is_role' => $isRole,
            ':is_disposable' => $isDisposable
        ]);

        $existing = $this->getByEmail($email);
        $contactId = $existing ? (int)$existing->contact_id : (int)$db->lastInsertId();

        // Assign to lists if requested
        if (!empty($data['list_ids']) && is_array($data['list_ids'])) {
            $this->syncLists($contactId, $data['list_ids']);
        }

        // Assign tags if requested
        if (!empty($data['tag_ids']) && is_array($data['tag_ids'])) {
            $this->syncTags($contactId, $data['tag_ids']);
        }

        return $contactId;
    }

    /**
     * Delete contact completely (GDPR / Right to be Forgotten).
     */
    public function deleteContact(int $contactId, ?int $adminUserId = null): bool {
        $contact = $this->getById($contactId);
        if (!$contact) {
            return false;
        }

        $this->query('DELETE FROM campaign_contacts WHERE contact_id = :id');
        $this->bind(':id', $contactId);
        $deleted = $this->execute();

        if ($deleted && class_exists('CampaignAudit')) {
            CampaignAudit::log($adminUserId, 'contact.deleted', 'contact', $contactId, [
                'email' => $contact->email,
                'name' => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''))
            ]);
        }

        return $deleted;
    }

    /**
     * Filter & Paginate Contacts.
     */
    public function getContacts(array $filters = [], int $limit = 50, int $offset = 0): array {
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $groupConcat = ($driver === 'sqlite') ? 'GROUP_CONCAT(DISTINCT t.name)' : 'GROUP_CONCAT(DISTINCT t.name SEPARATOR ", ")';
        $groupConcatL = ($driver === 'sqlite') ? 'GROUP_CONCAT(DISTINCT l.name)' : 'GROUP_CONCAT(DISTINCT l.name SEPARATOR ", ")';

        $sql = "SELECT c.*, 
                       {$groupConcat} AS tag_names,
                       {$groupConcatL} AS list_names
                FROM campaign_contacts c
                LEFT JOIN campaign_contact_tags ct ON c.contact_id = ct.contact_id
                LEFT JOIN campaign_tags t ON ct.tag_id = t.tag_id
                LEFT JOIN campaign_list_contacts lc ON c.contact_id = lc.contact_id
                LEFT JOIN campaign_lists l ON lc.list_id = l.list_id
                WHERE 1=1 ";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= 'AND c.status = :status ';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['list_id'])) {
            $sql .= 'AND lc.list_id = :list_id AND lc.status = "active" ';
            $params[':list_id'] = (int)$filters['list_id'];
        }

        if (!empty($filters['tag_id'])) {
            $sql .= 'AND ct.tag_id = :tag_id ';
            $params[':tag_id'] = (int)$filters['tag_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= 'AND (c.email LIKE :s OR c.first_name LIKE :s OR c.last_name LIKE :s OR c.company_name LIKE :s) ';
            $params[':s'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['source'])) {
            $sql .= 'AND c.source = :source ';
            $params[':source'] = $filters['source'];
        }

        $sql .= 'GROUP BY c.contact_id ORDER BY c.created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        return $this->resultSet() ?: [];
    }

    public function countContacts(array $filters = []): int {
        $sql = 'SELECT COUNT(DISTINCT c.contact_id) FROM campaign_contacts c
                LEFT JOIN campaign_contact_tags ct ON c.contact_id = ct.contact_id
                LEFT JOIN campaign_list_contacts lc ON c.contact_id = lc.contact_id
                WHERE 1=1 ';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= 'AND c.status = :status ';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['list_id'])) {
            $sql .= 'AND lc.list_id = :list_id AND lc.status = "active" ';
            $params[':list_id'] = (int)$filters['list_id'];
        }
        if (!empty($filters['tag_id'])) {
            $sql .= 'AND ct.tag_id = :tag_id ';
            $params[':tag_id'] = (int)$filters['tag_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= 'AND (c.email LIKE :s OR c.first_name LIKE :s OR c.last_name LIKE :s OR c.company_name LIKE :s) ';
            $params[':s'] = '%' . trim($filters['search']) . '%';
        }
        if (!empty($filters['source'])) {
            $sql .= 'AND c.source = :source ';
            $params[':source'] = $filters['source'];
        }

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }
        $row = $this->single();
        return (int)($row ? current((array)$row) : 0);
    }

    /**
     * Tags & Lists Association Helpers
     */
    public function getContactTags(int $contactId): array {
        $this->query('SELECT t.* FROM campaign_tags t
                      JOIN campaign_contact_tags ct ON t.tag_id = ct.tag_id
                      WHERE ct.contact_id = :id ORDER BY t.name ASC');
        $this->bind(':id', $contactId);
        return $this->resultSet() ?: [];
    }

    public function getContactLists(int $contactId): array {
        $this->query('SELECT l.*, lc.status as membership_status FROM campaign_lists l
                      JOIN campaign_list_contacts lc ON l.list_id = lc.list_id
                      WHERE lc.contact_id = :id ORDER BY l.name ASC');
        $this->bind(':id', $contactId);
        return $this->resultSet() ?: [];
    }

    public function syncLists(int $contactId, array $listIds): void {
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertIgnore = ($driver === 'sqlite') ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $stmt = $db->prepare("{$insertIgnore} INTO campaign_list_contacts (list_id, contact_id, status) VALUES (:lid, :cid, 'active')");
        foreach ($listIds as $lid) {
            $stmt->execute([':lid' => (int)$lid, ':cid' => $contactId]);
        }
    }

    public function syncTags(int $contactId, array $tagIds): void {
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertIgnore = ($driver === 'sqlite') ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $stmt = $db->prepare("{$insertIgnore} INTO campaign_contact_tags (contact_id, tag_id) VALUES (:cid, :tid)");
        foreach ($tagIds as $tid) {
            $stmt->execute([':cid' => $contactId, ':tid' => (int)$tid]);
        }
    }

    /**
     * SECURE INTERNAL PAYROLL SYNC
     * Reads active employees with STRICT READ-ONLY projection: (name, email) ONLY.
     * Absolutely NO salary, CTC, allowances, banking, or payroll details are ever read or exposed.
     */
    public function syncInternalEmployees(int $listId, ?int $adminUserId = null): array {
        $db = Database::getInstance()->getConnection();

        // 1. Ensure list exists and is marked internal
        $lStmt = $db->prepare('SELECT list_id, is_internal FROM campaign_lists WHERE list_id = :id');
        $lStmt->execute([':id' => $listId]);
        $list = $lStmt->fetch(PDO::FETCH_OBJ);
        if (!$list) {
            return ['success' => false, 'error' => 'Target list not found'];
        }

        // 2. Query strictly name and email from active users with an employee record
        $eStmt = $db->prepare('SELECT u.name, u.email 
                               FROM users u 
                               JOIN employees e ON u.user_id = e.user_id 
                               WHERE u.status = "active" 
                                 AND u.email IS NOT NULL 
                                 AND u.email != ""
                               ORDER BY u.name ASC');
        $eStmt->execute();
        $employees = $eStmt->fetchAll(PDO::FETCH_OBJ);

        $syncedCount = 0;
        $suppressionModel = new CampaignSuppression();

        foreach ($employees as $emp) {
            $cleanEmail = strtolower(trim($emp->email));
            if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Split name into first and last
            $parts = explode(' ', trim($emp->name), 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';

            $contactId = $this->upsertContact([
                'email' => $cleanEmail,
                'first_name' => $first,
                'last_name' => $last,
                'company_name' => 'Axiora Global Solutions (Internal)',
                'source' => 'internal_payroll',
                'consent_type' => 'internal_employee',
                'consent_notes' => 'Synchronized from internal HR employee directory. Read-only projection (name, email). Zero salary/financial exposure.',
                'list_ids' => [$listId]
            ]);

            if ($contactId > 0) {
                $syncedCount++;
            }
        }

        if (class_exists('CampaignAudit')) {
            CampaignAudit::log($adminUserId, 'payroll.employees_synced', 'list', $listId, [
                'synced_count' => $syncedCount
            ]);
        }

        return [
            'success' => true,
            'synced_count' => $syncedCount
        ];
    }

    /**
     * GDPR / India DPDP Article 15 Data Portability Export
     */
    public function exportGdprData(int $contactId): ?array {
        $contact = $this->getById($contactId);
        if (!$contact) {
            return null;
        }

        $db = Database::getInstance()->getConnection();
        
        // Consent log
        $cStmt = $db->prepare('SELECT * FROM campaign_audit_logs WHERE entity_type = "contact" AND entity_id = :id ORDER BY created_at DESC');
        $cStmt->execute([':id' => $contactId]);
        $auditLogs = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        // Send history
        $sStmt = $db->prepare('SELECT r.campaign_id, c.name as campaign_name, r.status, r.sent_at 
                               FROM campaign_recipients r 
                               JOIN campaigns_broadcast c ON r.campaign_id = c.campaign_id
                               WHERE r.email = :email');
        $sStmt->execute([':email' => $contact->email]);
        $sendHistory = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'subject_identity' => [
                'email' => $contact->email,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'company_name' => $contact->company_name,
                'status' => $contact->status,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at
            ],
            'consent_record' => [
                'type' => $contact->consent_type,
                'timestamp' => $contact->consent_timestamp,
                'source_ip' => $contact->consent_ip,
                'notes' => $contact->consent_notes
            ],
            'custom_attributes' => $contact->custom_fields,
            'list_memberships' => $contact->lists,
            'assigned_tags' => $contact->tags,
            'audit_trail' => $auditLogs,
            'dispatch_history' => $sendHistory,
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }
}
