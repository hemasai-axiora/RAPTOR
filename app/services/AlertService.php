<?php
/**
 * Raptor CRM - Deliverability Alert Lifecycle Service
 * -------------------------------------------------------------------------
 * Manages the full state machine for deliverability alerts:
 * DETECTED -> OPEN -> ACKNOWLEDGED -> RESOLVED / WAIVED -> REOPENED
 */

declare(strict_types=1);

class AlertService {

    public const STATE_OPEN = 'OPEN';
    public const STATE_ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const STATE_RESOLVED = 'RESOLVED';
    public const STATE_WAIVED = 'WAIVED';
    public const STATE_REOPENED = 'REOPENED';

    /**
     * Record or update an alert based on its deduplication key.
     */
    public static function recordAlert(
        string $dedupKey,
        string $objectType,
        ?int $objectId,
        string $ruleId,
        string $severity,
        string $message,
        array $evidence = [],
        string $policyVersion = '2026-11-09.1'
    ): int {
        $db = Database::getInstance()->getConnection();
        $now = date('Y-m-d H:i:s');

        // Check if alert with same dedup key already exists
        $stmt = $db->prepare('SELECT id, state, affected_count FROM marketing_alerts WHERE alert_dedup_key = :key LIMIT 1');
        $stmt->execute([':key' => $dedupKey]);
        $existing = $stmt->fetch(PDO::FETCH_OBJ);

        if ($existing) {
            $alertId = (int)$existing->id;
            // If already resolved, reopen it
            $newState = ($existing->state === self::STATE_RESOLVED) ? self::STATE_REOPENED : $existing->state;
            $newCount = (int)$existing->affected_count + 1;

            $uStmt = $db->prepare('UPDATE marketing_alerts 
                                   SET state = :state, affected_count = :cnt, last_seen_at = :last, evidence_json = :ev
                                   WHERE id = :id');
            $uStmt->execute([
                ':state' => $newState,
                ':cnt' => $newCount,
                ':last' => $now,
                ':ev' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
                ':id' => $alertId
            ]);
            return $alertId;
        }

        // Insert new alert
        $ins = $db->prepare('INSERT INTO marketing_alerts 
                             (alert_dedup_key, object_type, object_id, rule_id, policy_version, severity, state,
                              evidence_json, affected_count, first_seen_at, last_seen_at)
                             VALUES 
                             (:key, :otype, :oid, :rid, :pver, :sev, "OPEN", :ev, 1, :first, :last)');
        $ins->execute([
            ':key' => $dedupKey,
            ':otype' => $objectType,
            ':oid' => $objectId,
            ':rid' => $ruleId,
            ':pver' => $policyVersion,
            ':sev' => strtoupper($severity),
            ':ev' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
            ':first' => $now,
            ':last' => $now
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Acknowledge an alert.
     */
    public static function acknowledgeAlert(int $alertId, int $userId): bool {
        $db = Database::getInstance()->getConnection();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE marketing_alerts 
                              SET state = "ACKNOWLEDGED", acknowledged_by = :uid, acknowledged_at = :now 
                              WHERE id = :id AND state IN ("OPEN", "REOPENED")');
        return $stmt->execute([':uid' => $userId, ':now' => $now, ':id' => $alertId]);
    }

    /**
     * Resolve an alert.
     */
    public static function resolveAlert(int $alertId, int $userId, string $resolutionNote = ''): bool {
        $db = Database::getInstance()->getConnection();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE marketing_alerts 
                              SET state = "RESOLVED", resolved_by = :uid, resolved_at = :now, resolution_note = :note 
                              WHERE id = :id');
        return $stmt->execute([':uid' => $userId, ':now' => $now, ':note' => $resolutionNote, ':id' => $alertId]);
    }

    /**
     * Waive a non-blocking alert (with documented reason and expiry).
     */
    public static function waiveAlert(int $alertId, int $userId, string $reason, string $expiryDate): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT severity FROM marketing_alerts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $alertId]);
        $alert = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$alert || strtoupper($alert->severity) === 'BLOCK') {
            // Non-negotiable: BLOCK alerts cannot be waived
            return false;
        }

        $uStmt = $db->prepare('UPDATE marketing_alerts 
                               SET state = "WAIVED", waiver_reason = :reason, waiver_expiry = :expiry, resolved_by = :uid 
                               WHERE id = :id');
        return $uStmt->execute([
            ':reason' => $reason,
            ':expiry' => $expiryDate,
            ':uid' => $userId,
            ':id' => $alertId
        ]);
    }

    /**
     * Synchronize Preflight findings into alerts store.
     */
    public static function syncFindingsToAlerts(string $objectType, int $objectId, array $findings): void {
        foreach ($findings as $f) {
            $ruleId = is_object($f) ? ($f->ruleId ?? '') : ($f['rule_id'] ?? '');
            $severity = is_object($f) ? ($f->severity ?? 'INFO') : ($f['severity'] ?? 'INFO');
            $message = is_object($f) ? ($f->message ?? '') : ($f['message'] ?? '');
            $evidence = is_object($f) ? ($f->evidence ?? []) : ($f['evidence'] ?? []);
            $dedupKey = hash('sha256', "{$objectType}:{$objectId}:{$ruleId}");

            self::recordAlert(
                dedupKey: $dedupKey,
                objectType: $objectType,
                objectId: $objectId,
                ruleId: $ruleId,
                severity: $severity,
                message: $message,
                evidence: $evidence
            );
        }
    }

    /**
     * Get active/open alerts.
     */
    public static function getActiveAlerts(int $limit = 50, ?string $severity = null): array {
        $db = Database::getInstance()->getConnection();
        $sql = 'SELECT * FROM marketing_alerts WHERE state IN ("OPEN", "ACKNOWLEDGED", "REOPENED") ';
        $params = [];

        if (!empty($severity)) {
            $sql .= 'AND severity = :sev ';
            $params[':sev'] = strtoupper($severity);
        }

        $sql .= 'ORDER BY FIELD(severity, "BLOCK", "HIGH", "WARNING", "INFO"), last_seen_at DESC LIMIT ' . (int)$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
    }

    public static function countActiveAlerts(): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query('SELECT severity, COUNT(*) as cnt 
                            FROM marketing_alerts 
                            WHERE state IN ("OPEN", "ACKNOWLEDGED", "REOPENED") 
                            GROUP BY severity');
        $counts = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        return [
            'block' => (int)($counts['BLOCK'] ?? 0),
            'high' => (int)($counts['HIGH'] ?? 0),
            'warning' => (int)($counts['WARNING'] ?? 0),
            'info' => (int)($counts['INFO'] ?? 0),
            'total' => array_sum(array_map('intval', $counts))
        ];
    }
}

