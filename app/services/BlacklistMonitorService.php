<?php
/**
 * Raptor CRM - DNSBL Blacklist Monitoring Service
 * 
 * Inspects sending IPs and domains across premier public Real-time Blackhole Lists (DNSBLs)
 * and Domain Blocklists (DBLs) to protect sender reputation before delivery fails.
 */

class BlacklistMonitorService {

    /** @var callable|null Mock resolver for automated testing */
    private static $dnsResolver = null;

    public const IP_BLACKLISTS = [
        'zen.spamhaus.org' => [
            'name' => 'Spamhaus ZEN',
            'delist_url' => 'https://www.spamhaus.org/lookup/',
            'severity' => 'critical'
        ],
        'b.barracudacentral.org' => [
            'name' => 'Barracuda Reputation Block List (BRBL)',
            'delist_url' => 'https://www.barracudacentral.org/rbl/removal-request',
            'severity' => 'high'
        ],
        'bl.spamcop.net' => [
            'name' => 'SpamCop Blocking List (SCBL)',
            'delist_url' => 'https://www.spamcop.net/bl.shtml',
            'severity' => 'high'
        ],
        'dnsbl.sorbs.net' => [
            'name' => 'SORBS (Spam and Open Relay Blocking System)',
            'delist_url' => 'http://www.sorbs.net/delisting/overview.shtml',
            'severity' => 'medium'
        ],
        'dnsbl-1.uceprotect.net' => [
            'name' => 'UCEPROTECT Level 1',
            'delist_url' => 'http://www.uceprotect.net/en/rblcheck.php',
            'severity' => 'medium'
        ]
    ];

    public const DOMAIN_BLACKLISTS = [
        'dbl.spamhaus.org' => [
            'name' => 'Spamhaus DBL (Domain Blocklist)',
            'delist_url' => 'https://www.spamhaus.org/lookup/',
            'severity' => 'critical'
        ],
        'multi.surbl.org' => [
            'name' => 'SURBL Multi',
            'delist_url' => 'http://www.surbl.org/surbl-analysis',
            'severity' => 'high'
        ]
    ];

    public static function setDnsResolver(?callable $resolver): void {
        self::$dnsResolver = $resolver;
    }

    /**
     * Check if an IP address is listed on major DNSBLs.
     */
    public static function checkIp(string $ip): array {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['success' => false, 'error' => 'Invalid IPv4 address provided'];
        }

        // Reverse octets: 192.0.2.1 -> 1.2.0.192
        $parts = explode('.', $ip);
        $reverseIp = implode('.', array_reverse($parts));

        $results = [];
        $isClean = true;

        foreach (self::IP_BLACKLISTS as $dnsblHost => $meta) {
            $lookupHost = "{$reverseIp}.{$dnsblHost}";
            $records = self::queryA($lookupHost);

            $isListed = false;
            $returnCode = null;

            if (!empty($records)) {
                $returnCode = $records[0]['ip'] ?? null;
                // Standard DNSBL return codes are in the 127.0.0.0/8 range
                if ($returnCode && str_starts_with($returnCode, '127.0.0.')) {
                    $isListed = true;
                    $isClean = false;
                }
            }

            $results[$dnsblHost] = [
                'name' => $meta['name'],
                'listed' => $isListed,
                'response_code' => $returnCode,
                'meaning' => self::interpretSpamhausCode($returnCode),
                'delist_url' => $meta['delist_url'],
                'severity' => $meta['severity']
            ];
        }

        return [
            'success' => true,
            'ip' => $ip,
            'is_clean' => $isClean,
            'listed_count' => count(array_filter($results, fn($r) => $r['listed'])),
            'details' => $results
        ];
    }

    /**
     * Check if a domain is listed on domain blocklists (DBLs).
     */
    public static function checkDomain(string $domain): array {
        $cleanDomain = strtolower(trim($domain));
        if (empty($cleanDomain)) {
            return ['success' => false, 'error' => 'Domain cannot be empty'];
        }

        $results = [];
        $isClean = true;

        foreach (self::DOMAIN_BLACKLISTS as $dblHost => $meta) {
            $lookupHost = "{$cleanDomain}.{$dblHost}";
            $records = self::queryA($lookupHost);

            $isListed = false;
            $returnCode = null;

            if (!empty($records)) {
                $returnCode = $records[0]['ip'] ?? null;
                if ($returnCode && str_starts_with($returnCode, '127.0.')) {
                    $isListed = true;
                    $isClean = false;
                }
            }

            $results[$dblHost] = [
                'name' => $meta['name'],
                'listed' => $isListed,
                'response_code' => $returnCode,
                'delist_url' => $meta['delist_url'],
                'severity' => $meta['severity']
            ];
        }

        return [
            'success' => true,
            'domain' => $cleanDomain,
            'is_clean' => $isClean,
            'listed_count' => count(array_filter($results, fn($r) => $r['listed'])),
            'details' => $results
        ];
    }

    private static function queryA(string $hostname): array {
        if (self::$dnsResolver !== null) {
            return call_user_func(self::$dnsResolver, $hostname, DNS_A);
        }

        $records = @dns_get_record($hostname, DNS_A);
        return is_array($records) ? $records : [];
    }

    private static function interpretSpamhausCode(?string $code): string {
        return match($code) {
            '127.0.0.2' => 'SBL - Direct Unsolicited Bulk Email source',
            '127.0.0.3' => 'CSS - Snowshoe spamming operations',
            '127.0.0.4', '127.0.0.5', '127.0.0.6', '127.0.0.7' => 'XBL - Compromised machine, trojan, or proxy exploit',
            '127.0.0.10', '127.0.0.11' => 'PBL - End-user / dynamic IP range (must send via authentic relay)',
            default => $code ? "Listed with return code: {$code}" : 'Clean / Not Listed'
        };
    }
}
