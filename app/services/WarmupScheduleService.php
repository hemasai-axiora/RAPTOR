<?php
/**
 * Raptor CRM - IP & Domain Warm-Up Schedule Engine
 * 
 * Protects sender reputation by enforcing gradual volume ramp-up over 4-6 weeks:
 * Stage 1: 50 emails/day
 * Stage 2: 100 emails/day
 * Stage 3: 250 emails/day
 * Stage 4: 500 emails/day
 * Stage 5: 1,000 emails/day
 * Stage 6: 2,500 emails/day
 * Stage 7: 5,000+ emails/day (Fully warmed)
 */

class WarmupScheduleService {

    public const STAGES = [
        1 => ['days' => 3, 'cap' => 50],
        2 => ['days' => 4, 'cap' => 100],
        3 => ['days' => 7, 'cap' => 250],
        4 => ['days' => 7, 'cap' => 500],
        5 => ['days' => 7, 'cap' => 1000],
        6 => ['days' => 7, 'cap' => 2500],
        7 => ['days' => 999, 'cap' => 10000]
    ];

    /**
     * Check if domain has available daily warm-up quota.
     */
    public static function canSend(string $domain): bool {
        $status = self::getDailyStatus($domain);
        return $status['remaining_today'] > 0;
    }

    /**
     * Record a successful dispatch against domain's daily warm-up counter.
     */
    public static function recordSend(string $domain): void {
        $cleanDomain = strtolower(trim($domain));
        if (empty($cleanDomain)) return;

        $db = Database::getInstance()->getConnection();
        $today = date('Y-m-d');
        $domainId = self::resolveDomainId($cleanDomain);

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO campaign_warmup_schedules (domain_id, current_stage, daily_cap, sent_today, date_stamp)
                    VALUES (:did, 1, 50, 1, :today)
                    ON CONFLICT(domain_id, date_stamp) DO UPDATE SET 
                        sent_today = sent_today + 1';
        } else {
            $sql = 'INSERT INTO campaign_warmup_schedules (domain_id, current_stage, daily_cap, sent_today, date_stamp)
                    VALUES (:did, 1, 50, 1, :today)
                    ON DUPLICATE KEY UPDATE 
                        sent_today = sent_today + 1';
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([':did' => $domainId, ':today' => $today]);
        } catch (Throwable $e) {}
    }

    /**
     * Get current stage, daily cap, and remaining quota for a domain.
     */
    public static function getDailyStatus(string $domain): array {
        $cleanDomain = strtolower(trim($domain));
        $today = date('Y-m-d');
        $db = Database::getInstance()->getConnection();
        $domainId = self::resolveDomainId($cleanDomain);

        // Fetch current active record for today
        $stmt = $db->prepare('SELECT * FROM campaign_warmup_schedules WHERE domain_id = :did AND date_stamp = :today LIMIT 1');
        $stmt->execute([':did' => $domainId, ':today' => $today]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$row) {
            // Determine active stage based on total history
            $stage = self::calculateCurrentStage($domainId);
            $cap = self::STAGES[$stage]['cap'] ?? 50;
            return [
                'stage' => $stage,
                'daily_cap' => $cap,
                'sent_today' => 0,
                'remaining_today' => $cap,
                'date' => $today
            ];
        }

        $cap = (int)$row->daily_cap;
        $sent = (int)$row->sent_today;
        return [
            'stage' => (int)$row->current_stage,
            'daily_cap' => $cap,
            'sent_today' => $sent,
            'remaining_today' => max(0, $cap - $sent),
            'date' => $today
        ];
    }

    /**
     * Resolve or register domain_id in campaign_domains table.
     */
    public static function resolveDomainId(string $domain): int {
        $cleanDomain = strtolower(trim($domain));
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT domain_id FROM campaign_domains WHERE domain_name = :d LIMIT 1');
        $stmt->execute([':d' => $cleanDomain]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if ($row) {
            return (int)$row->domain_id;
        }

        $ins = $db->prepare('INSERT INTO campaign_domains (domain_name, is_default) VALUES (:d, 0)');
        try {
            $ins->execute([':d' => $cleanDomain]);
            return (int)$db->lastInsertId();
        } catch (Throwable $e) {
            $stmt->execute([':d' => $cleanDomain]);
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return (int)($row ? $row->domain_id : 1);
        }
    }

    /**
     * Calculate what warm-up stage a domain has achieved based on days active.
     */
    private static function calculateCurrentStage(int $domainId): int {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT COUNT(DISTINCT date_stamp) as days_active FROM campaign_warmup_schedules WHERE domain_id = :did AND sent_today >= 10');
        $stmt->execute([':did' => $domainId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        $days = (int)($row ? $row->days_active : 0);

        if ($days >= 35) return 7;
        if ($days >= 28) return 6;
        if ($days >= 21) return 5;
        if ($days >= 14) return 4;
        if ($days >= 7) return 3;
        if ($days >= 3) return 2;
        return 1;
    }
}

