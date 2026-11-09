<?php
/**
 * Raptor CRM - Marketing Consent & Compliance Ledger Service
 * -------------------------------------------------------------------------
 * Append-only compliance ledger for GDPR, Indian DPDP, and CAN-SPAM.
 * Tracks explicit grants, revocations, complaints, and bounces per purpose & channel.
 * 
 * Strict boundary invariant:
 * Marketing consent records are completely segregated from employee HR and payroll records.
 */

declare(strict_types=1);

class ConsentService {

    public const CHANNEL_EMAIL = 'email';
    public const PURPOSE_MARKETING = 'marketing_newsletter';
    public const PURPOSE_WEBINAR = 'webinar_updates';
    public const PURPOSE_PRODUCT = 'product_announcements';

    public const ACTION_GRANT = 'grant';
    public const ACTION_REVOKE = 'revoke';
    public const ACTION_COMPLAINT = 'complaint';
    public const ACTION_BOUNCE = 'hard_bounce';

    /**
     * Append a consent event to the append-only ledger.
     */
    public static function appendEvent(
        int $contactId,
        string $action,
        string $purpose = self::PURPOSE_MARKETING,
        string $channel = self::CHANNEL_EMAIL,
        ?string $legalBasis = 'explicit_consent',
        ?string $evidenceRef = null,
        ?string $noticeVersion = '2026.1',
        ?int $brandId = null
    ): int {
        $db = Database::getInstance()->getConnection();
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare('INSERT INTO marketing_consent_events 
                              (contact_id, brand_id, channel, purpose, action, legal_basis, evidence_ref, notice_version, occurred_at)
                              VALUES 
                              (:cid, :bid, :channel, :purpose, :action, :basis, :evidence, :notice, :occurred)');
        $stmt->execute([
            ':cid' => $contactId,
            ':bid' => $brandId,
            ':channel' => $channel,
            ':purpose' => $purpose,
            ':action' => $action,
            ':basis' => $legalBasis,
            ':evidence' => $evidenceRef,
            ':notice' => $noticeVersion,
            ':occurred' => $now
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Grant consent for a contact.
     */
    public static function grant(int $contactId, string $purpose = self::PURPOSE_MARKETING, ?string $evidenceRef = null): int {
        return self::appendEvent(
            contactId: $contactId,
            action: self::ACTION_GRANT,
            purpose: $purpose,
            legalBasis: 'opt_in_form',
            evidenceRef: $evidenceRef ?: 'User web opt-in'
        );
    }

    /**
     * Revoke consent for a contact (e.g. from unsubscribe or preference center).
     */
    public static function revoke(int $contactId, string $purpose = self::PURPOSE_MARKETING, ?string $evidenceRef = null): int {
        return self::appendEvent(
            contactId: $contactId,
            action: self::ACTION_REVOKE,
            purpose: $purpose,
            legalBasis: 'user_opt_out',
            evidenceRef: $evidenceRef ?: 'Unsubscribe request'
        );
    }

    /**
     * Check if a contact currently has active consent for a given purpose.
     * Evaluates latest event in the append-only ledger.
     */
    public static function hasActiveConsent(int $contactId, string $purpose = self::PURPOSE_MARKETING, string $channel = self::CHANNEL_EMAIL): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT action FROM marketing_consent_events 
                              WHERE contact_id = :cid AND channel = :chan AND purpose = :purp 
                              ORDER BY occurred_at DESC, id DESC LIMIT 1');
        $stmt->execute([
            ':cid' => $contactId,
            ':chan' => $channel,
            ':purp' => $purpose
        ]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$row) {
            // Check default contact opt-in status from campaign_contacts table
            $cStmt = $db->prepare('SELECT status, consent_type FROM campaign_contacts WHERE contact_id = :cid LIMIT 1');
            $cStmt->execute([':cid' => $contactId]);
            $contact = $cStmt->fetch(PDO::FETCH_OBJ);
            return $contact && $contact->status === 'subscribed';
        }

        return $row->action === self::ACTION_GRANT;
    }
}

