<?php
/**
 * Migration 0050: Unified Customer 360 Architecture Schema
 * --------------------------------------------------------------------------
 * Establishes the core data foundation for the Marketing Management Platform:
 * 1. Master `contacts` table (single source of truth for leads, customers, subscribers)
 * 2. Multi-channel `contact_identities` (email, phone, whatsapp, cookie, social IDs)
 * 3. Universal `contact_activities` (chronological timeline of all touchpoints)
 * 4. Links `leads`, `customers`, and `campaign_contacts` to unified contacts
 * 5. Backfills existing records, linking identities and bridging suppression status
 */

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Starting Migration 0050: Unified Customer 360 Architecture Schema...\n";

if ($driver === 'sqlite') {
    // 1. Contacts Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS contacts (
        contact_id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact_uuid TEXT NOT NULL UNIQUE,
        primary_email TEXT NULL,
        primary_phone TEXT NULL,
        first_name TEXT NULL,
        last_name TEXT NULL,
        company_name TEXT NULL,
        lifecycle_stage TEXT NOT NULL DEFAULT 'lead',
        lead_score INTEGER NOT NULL DEFAULT 0,
        lead_quality TEXT NOT NULL DEFAULT 'warm',
        assigned_user_id INTEGER NULL,
        is_suppressed INTEGER NOT NULL DEFAULT 0,
        suppression_reason TEXT NULL,
        suppressed_at DATETIME NULL,
        custom_fields_json TEXT NULL,
        source TEXT NULL,
        utm_source TEXT NULL,
        utm_medium TEXT NULL,
        utm_campaign TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_contact_email ON contacts (primary_email);
    CREATE INDEX IF NOT EXISTS idx_contact_phone ON contacts (primary_phone);
    CREATE INDEX IF NOT EXISTS idx_contact_stage ON contacts (lifecycle_stage);
    CREATE INDEX IF NOT EXISTS idx_contact_assigned ON contacts (assigned_user_id);
    CREATE INDEX IF NOT EXISTS idx_contact_suppressed ON contacts (is_suppressed);
    ");

    // 2. Identities Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS contact_identities (
        identity_id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact_id INTEGER NOT NULL,
        channel TEXT NOT NULL,
        identity_value TEXT NOT NULL,
        is_primary INTEGER NOT NULL DEFAULT 0,
        verified_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (channel, identity_value),
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_identity_contact ON contact_identities (contact_id);
    ");

    // 3. Activities Table (SQLite)
    $db->exec("
    CREATE TABLE IF NOT EXISTS contact_activities (
        activity_id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact_id INTEGER NOT NULL,
        channel TEXT NOT NULL,
        activity_type TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT NULL,
        payload_json TEXT NULL,
        actor_user_id INTEGER NULL,
        occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    );
    CREATE INDEX IF NOT EXISTS idx_activity_contact ON contact_activities (contact_id, occurred_at);
    CREATE INDEX IF NOT EXISTS idx_activity_type ON contact_activities (activity_type);
    ");

    // Foreign key columns in existing tables (SQLite safe column additions)
    try { $db->exec("ALTER TABLE leads ADD COLUMN contact_id INTEGER NULL;"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE customers ADD COLUMN contact_id INTEGER NULL;"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE campaign_contacts ADD COLUMN unified_contact_id INTEGER NULL;"); } catch (Throwable $e) {}

} else {
    // 1. Contacts Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS contacts (
        contact_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_uuid CHAR(36) NOT NULL UNIQUE,
        primary_email VARCHAR(255) NULL,
        primary_phone VARCHAR(50) NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        company_name VARCHAR(150) NULL,
        lifecycle_stage ENUM('subscriber', 'lead', 'mql', 'sql', 'opportunity', 'customer', 'evangelist', 'churned') NOT NULL DEFAULT 'lead',
        lead_score INT NOT NULL DEFAULT 0,
        lead_quality ENUM('hot', 'warm', 'cold') NOT NULL DEFAULT 'warm',
        assigned_user_id INT NULL,
        is_suppressed TINYINT(1) NOT NULL DEFAULT 0,
        suppression_reason VARCHAR(100) NULL,
        suppressed_at DATETIME NULL,
        custom_fields_json LONGTEXT NULL,
        source VARCHAR(100) NULL,
        utm_source VARCHAR(100) NULL,
        utm_medium VARCHAR(100) NULL,
        utm_campaign VARCHAR(100) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_contact_email (primary_email),
        INDEX idx_contact_phone (primary_phone),
        INDEX idx_contact_stage (lifecycle_stage),
        INDEX idx_contact_assigned (assigned_user_id),
        INDEX idx_contact_suppressed (is_suppressed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Identities Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS contact_identities (
        identity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id BIGINT UNSIGNED NOT NULL,
        channel ENUM('email', 'phone', 'whatsapp', 'cookie', 'meta_psid', 'linkedin_id') NOT NULL,
        identity_value VARCHAR(255) NOT NULL,
        is_primary TINYINT(1) NOT NULL DEFAULT 0,
        verified_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_channel_val (channel, identity_value),
        INDEX idx_identity_contact (contact_id),
        CONSTRAINT fk_identities_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Activities Table (MySQL)
    $db->exec("
    CREATE TABLE IF NOT EXISTS contact_activities (
        activity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id BIGINT UNSIGNED NOT NULL,
        channel ENUM('web', 'form', 'email', 'whatsapp', 'call', 'sms', 'social', 'crm', 'invoice') NOT NULL,
        activity_type VARCHAR(80) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        payload_json LONGTEXT NULL,
        actor_user_id INT NULL,
        occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_contact (contact_id, occurred_at),
        INDEX idx_activity_type (activity_type),
        CONSTRAINT fk_activities_contact FOREIGN KEY (contact_id) REFERENCES contacts (contact_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Guarded column additions for MySQL
    try {
        $db->exec("ALTER TABLE leads ADD COLUMN contact_id BIGINT UNSIGNED NULL AFTER lead_id, ADD INDEX idx_leads_contact_id (contact_id);");
    } catch (Throwable $e) { /* Column or index already exists */ }

    try {
        $db->exec("ALTER TABLE customers ADD COLUMN contact_id BIGINT UNSIGNED NULL AFTER customer_id, ADD INDEX idx_cust_contact_id (contact_id);");
    } catch (Throwable $e) { /* Column or index already exists */ }

    try {
        $db->exec("ALTER TABLE campaign_contacts ADD COLUMN unified_contact_id BIGINT UNSIGNED NULL AFTER contact_id, ADD INDEX idx_camp_unified_contact_id (unified_contact_id);");
    } catch (Throwable $e) { /* Column or index already exists */ }
}

echo "  + Core Customer 360 tables created successfully.\n";

// Helper for generating UUID v4
$genUuid = function(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
};

// =========================================================================
// Automated Data Backfill & Linking
// =========================================================================
echo "  + Running automated backfill and identity unification...\n";

// 1. Backfill Customers
try {
    $custStmt = $db->query("SELECT customer_id, first_name, email, phone, company_name, owner_employee_id, created_at FROM customers WHERE contact_id IS NULL");
    $customers = $custStmt ? $custStmt->fetchAll(PDO::FETCH_OBJ) : [];
    
    foreach ($customers as $c) {
        $email = !empty($c->email) ? strtolower(trim($c->email)) : null;
        $phone = !empty($c->phone) ? preg_replace('/(?<!^)\+|[^\d+]/', '', trim($c->phone)) : null;
        $contactId = null;

        // Check if identity already exists
        if ($email) {
            $stmt = $db->prepare("SELECT contact_id FROM contact_identities WHERE channel = 'email' AND identity_value = ?");
            $stmt->execute([$email]);
            $contactId = $stmt->fetchColumn();
        }
        if (!$contactId && $phone) {
            $stmt = $db->prepare("SELECT contact_id FROM contact_identities WHERE channel = 'phone' AND identity_value = ?");
            $stmt->execute([$phone]);
            $contactId = $stmt->fetchColumn();
        }

        if (!$contactId) {
            $uuid = $genUuid();
            $ins = $db->prepare("INSERT INTO contacts (contact_uuid, primary_email, primary_phone, first_name, company_name, lifecycle_stage, source, created_at)
                                 VALUES (?, ?, ?, ?, ?, 'customer', 'customer_backfill', ?)");
            $ins->execute([$uuid, $email, $phone, $c->first_name ?? null, $c->company_name ?? null, $c->created_at ?? date('Y-m-d H:i:s')]);
            $contactId = (int)$db->lastInsertId();

            if ($email) {
                try {
                    $db->prepare("INSERT INTO contact_identities (contact_id, channel, identity_value, is_primary) VALUES (?, 'email', ?, 1)")
                       ->execute([$contactId, $email]);
                } catch (Throwable $e) {}
            }
            if ($phone) {
                try {
                    $db->prepare("INSERT INTO contact_identities (contact_id, channel, identity_value, is_primary) VALUES (?, 'phone', ?, 1)")
                       ->execute([$contactId, $phone]);
                } catch (Throwable $e) {}
            }
        }

        $db->prepare("UPDATE customers SET contact_id = ? WHERE customer_id = ?")->execute([$contactId, $c->customer_id]);
    }
    echo "    - Backfilled " . count($customers) . " customer records into contacts.\n";
} catch (Throwable $e) {
    echo "    - Notice on customer backfill: " . $e->getMessage() . "\n";
}

// 2. Backfill Leads
try {
    $leadCols = ($driver === 'sqlite') 
        ? $db->query("PRAGMA table_info(leads)")->fetchAll(PDO::FETCH_COLUMN, 1)
        : $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);

    $srcCol = in_array('source', $leadCols, true) ? 'source' : (in_array('lead_source', $leadCols, true) ? 'lead_source' : "'' AS source");
    $leadStmt = $db->query("SELECT lead_id, first_name, last_name, email, phone, company_name, status, lead_quality, owner_employee_id, {$srcCol} AS lead_src, created_at FROM leads WHERE contact_id IS NULL");
    $leads = $leadStmt ? $leadStmt->fetchAll(PDO::FETCH_OBJ) : [];

    foreach ($leads as $l) {
        $email = !empty($l->email) ? strtolower(trim($l->email)) : null;
        $phone = !empty($l->phone) ? preg_replace('/(?<!^)\+|[^\d+]/', '', trim($l->phone)) : null;
        $contactId = null;

        if ($email) {
            $stmt = $db->prepare("SELECT contact_id FROM contact_identities WHERE channel = 'email' AND identity_value = ?");
            $stmt->execute([$email]);
            $contactId = $stmt->fetchColumn();
        }
        if (!$contactId && $phone) {
            $stmt = $db->prepare("SELECT contact_id FROM contact_identities WHERE channel = 'phone' AND identity_value = ?");
            $stmt->execute([$phone]);
            $contactId = $stmt->fetchColumn();
        }

        if (!$contactId) {
            $uuid = $genUuid();
            $stage = ($l->status === 'converted') ? 'customer' : (($l->status === 'qualified' || $l->status === 'proposal') ? 'sql' : 'lead');
            $quality = in_array($l->lead_quality, ['hot', 'warm', 'cold']) ? $l->lead_quality : 'warm';
            
            $ins = $db->prepare("INSERT INTO contacts (contact_uuid, primary_email, primary_phone, first_name, last_name, company_name, lifecycle_stage, lead_quality, source, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$uuid, $email, $phone, $l->first_name ?? null, $l->last_name ?? null, $l->company_name ?? null, $stage, $quality, $l->lead_src ?? 'lead_backfill', $l->created_at ?? date('Y-m-d H:i:s')]);
            $contactId = (int)$db->lastInsertId();

            if ($email) {
                try {
                    $db->prepare("INSERT INTO contact_identities (contact_id, channel, identity_value, is_primary) VALUES (?, 'email', ?, 1)")
                       ->execute([$contactId, $email]);
                } catch (Throwable $e) {}
            }
            if ($phone) {
                try {
                    $db->prepare("INSERT INTO contact_identities (contact_id, channel, identity_value, is_primary) VALUES (?, 'phone', ?, 1)")
                       ->execute([$contactId, $phone]);
                } catch (Throwable $e) {}
            }
        }

        $db->prepare("UPDATE leads SET contact_id = ? WHERE lead_id = ?")->execute([$contactId, $l->lead_id]);
    }
    echo "    - Backfilled " . count($leads) . " lead records into contacts.\n";
} catch (Throwable $e) {
    echo "    - Notice on lead backfill: " . $e->getMessage() . "\n";
}

// 3. Backfill Campaign Contacts
try {
    $campStmt = $db->query("SELECT contact_id, email, first_name, last_name, company_name, status, source, created_at FROM campaign_contacts WHERE unified_contact_id IS NULL");
    $campContacts = $campStmt ? $campStmt->fetchAll(PDO::FETCH_OBJ) : [];

    foreach ($campContacts as $cc) {
        $email = !empty($cc->email) ? strtolower(trim($cc->email)) : null;
        if (!$email) continue;
        $contactId = null;

        $stmt = $db->prepare("SELECT contact_id FROM contact_identities WHERE channel = 'email' AND identity_value = ?");
        $stmt->execute([$email]);
        $contactId = $stmt->fetchColumn();

        if (!$contactId) {
            $uuid = $genUuid();
            $isSuppressed = in_array($cc->status, ['unsubscribed', 'bounced', 'complained', 'suppressed']) ? 1 : 0;
            $ins = $db->prepare("INSERT INTO contacts (contact_uuid, primary_email, first_name, last_name, company_name, lifecycle_stage, is_suppressed, suppression_reason, source, created_at)
                                 VALUES (?, ?, ?, ?, ?, 'subscriber', ?, ?, ?, ?)");
            $ins->execute([$uuid, $email, $cc->first_name ?? null, $cc->last_name ?? null, $cc->company_name ?? null, $isSuppressed, $isSuppressed ? $cc->status : null, $cc->source ?? 'campaign_backfill', $cc->created_at ?? date('Y-m-d H:i:s')]);
            $contactId = (int)$db->lastInsertId();

            try {
                $db->prepare("INSERT INTO contact_identities (contact_id, channel, identity_value, is_primary) VALUES (?, 'email', ?, 1)")
                   ->execute([$contactId, $email]);
            } catch (Throwable $e) {}
        }

        $db->prepare("UPDATE campaign_contacts SET unified_contact_id = ? WHERE contact_id = ?")->execute([$contactId, $cc->contact_id]);
    }
    echo "    - Backfilled " . count($campContacts) . " campaign contacts into contacts.\n";
} catch (Throwable $e) {
    echo "    - Notice on campaign contacts backfill: " . $e->getMessage() . "\n";
}

// 4. Propagate Existing Email Suppression to Contacts and Leads
try {
    $suppStmt = $db->query("SELECT email, reason, created_at FROM campaign_suppression_list");
    $suppressed = $suppStmt ? $suppStmt->fetchAll(PDO::FETCH_OBJ) : [];

    foreach ($suppressed as $s) {
        $email = strtolower(trim($s->email));
        // Suppress in master contacts
        $db->prepare("UPDATE contacts SET is_suppressed = 1, suppression_reason = ?, suppressed_at = ? WHERE primary_email = ?")
           ->execute([$s->reason ?? 'email_suppressed', $s->created_at ?? date('Y-m-d H:i:s'), $email]);
        // Update CRM leads to prevent cold-calling unsubscribed prospects
        $db->prepare("UPDATE leads SET status = 'unsubscribed' WHERE LOWER(email) = ? AND status != 'converted'")
           ->execute([$email]);
    }
    echo "    - Synced " . count($suppressed) . " suppressed emails to master contacts and CRM leads.\n";
} catch (Throwable $e) {
    echo "    - Notice on suppression sync: " . $e->getMessage() . "\n";
}

echo "Migration 0050 completed successfully!\n";
