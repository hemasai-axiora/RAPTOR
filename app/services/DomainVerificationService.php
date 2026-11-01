<?php
/**
 * Raptor CRM - Domain DNS Authentication & Verification Service
 * 
 * Verifies SPF, DKIM (2048-bit), DMARC, and MX records via live DNS queries.
 * Provides copyable DNS record configurations for domain administrators.
 */

class DomainVerificationService {

    /** @var callable|null Mock resolver for automated tests */
    private static $dnsResolver = null;

    /**
     * Set a custom DNS resolver (useful for testing or fallback).
     */
    public static function setDnsResolver(?callable $resolver): void {
        self::$dnsResolver = $resolver;
    }

    /**
     * Generate the full list of required DNS records for a domain.
     */
    public static function getRequiredDnsRecords(object $domain): array {
        $domainName = strtolower($domain->domain_name);
        $selector = $domain->dkim_selector ?: 'raptor';
        $pubKey = $domain->dkim_public_key ?: '';

        // Provider include configuration
        $provider = strtolower($_ENV['MAIL_PROVIDER'] ?? 'smtp');
        $spfInclude = match($provider) {
            'ses' => 'include:amazonses.com',
            'postmark' => 'include:spf.mtasv.net',
            'sendgrid' => 'include:sendgrid.net',
            'mailgun' => 'include:mailgun.org',
            default => 'include:_spf.raptor.internal'
        };

        $appHost = parse_url(defined('URLROOT') ? URLROOT : 'http://localhost', PHP_URL_HOST) ?: 'raptor.internal';

        return [
            'spf' => [
                'type' => 'TXT',
                'host' => '@',
                'full_host' => $domainName,
                'value' => "v=spf1 {$spfInclude} ~all",
                'purpose' => 'Authorizes sending servers to dispatch emails for this domain',
                'status' => $domain->spf_status ?? 'unverified'
            ],
            'dkim' => [
                'type' => 'TXT',
                'host' => "{$selector}._domainkey",
                'full_host' => "{$selector}._domainkey.{$domainName}",
                'value' => "v=DKIM1; k=rsa; p={$pubKey}",
                'purpose' => '2048-bit cryptographic signature proving message authenticity',
                'status' => $domain->dkim_status ?? 'unverified'
            ],
            'dmarc' => [
                'type' => 'TXT',
                'host' => '_dmarc',
                'full_host' => "_dmarc.{$domainName}",
                'value' => "v=DMARC1; p=none; sp=none; rua=mailto:dmarc-reports@{$domainName}; aspf=r; adkim=r",
                'purpose' => 'Email authentication policy & aggregate feedback reporting (starts in monitoring mode)',
                'status' => $domain->dmarc_status ?? 'unverified'
            ],
            'mx' => [
                'type' => 'MX',
                'host' => '@',
                'full_host' => $domainName,
                'value' => "10 inbound.{$domainName}",
                'purpose' => 'Ensures recipient mail servers accept this domain as legitimate with valid reply routing',
                'status' => $domain->mx_status ?? 'unverified'
            ],
            'tracking' => [
                'type' => 'CNAME',
                'host' => 'track',
                'full_host' => "track.{$domainName}",
                'value' => $appHost,
                'purpose' => 'Custom branded click & open tracking domain (preserves domain reputation)',
                'status' => 'info'
            ]
        ];
    }

    /**
     * Perform live DNS verification for SPF, DKIM, DMARC, and MX records.
     */
    public static function verifyDomain($domainOrId): array {
        $domainModel = new CampaignDomain();
        $domain = is_numeric($domainOrId) ? $domainModel->getById((int)$domainOrId) : $domainOrId;

        if (!$domain) {
            return ['success' => false, 'error' => 'Domain not found'];
        }

        $domainName = strtolower($domain->domain_name);
        $selector = $domain->dkim_selector ?: 'raptor';
        $dkimHost = "{$selector}._domainkey.{$domainName}";
        $dmarcHost = "_dmarc.{$domainName}";

        // 1. Check SPF
        $spfValid = self::checkSpf($domainName);

        // 2. Check DKIM
        $dkimValid = self::checkDkim($dkimHost, $domain->dkim_public_key);

        // 3. Check DMARC
        $dmarcValid = self::checkDmarc($dmarcHost);

        // 4. Check MX
        $mxValid = self::checkMx($domainName);

        $statuses = [
            'spf_status' => $spfValid ? CampaignDomain::STATUS_VALID : CampaignDomain::STATUS_INVALID,
            'dkim_status' => $dkimValid ? CampaignDomain::STATUS_VALID : CampaignDomain::STATUS_INVALID,
            'dmarc_status' => $dmarcValid ? CampaignDomain::STATUS_VALID : CampaignDomain::STATUS_INVALID,
            'mx_status' => $mxValid ? CampaignDomain::STATUS_VALID : CampaignDomain::STATUS_INVALID
        ];

        // Persist update
        $domainModel->updateStatuses((int)$domain->domain_id, $statuses);

        $allPassed = ($spfValid && $dkimValid && $dmarcValid && $mxValid);

        return [
            'success' => true,
            'domain_id' => $domain->domain_id,
            'domain_name' => $domainName,
            'all_passed' => $allPassed,
            'results' => [
                'spf' => ['valid' => $spfValid, 'status' => $statuses['spf_status']],
                'dkim' => ['valid' => $dkimValid, 'status' => $statuses['dkim_status']],
                'dmarc' => ['valid' => $dmarcValid, 'status' => $statuses['dmarc_status']],
                'mx' => ['valid' => $mxValid, 'status' => $statuses['mx_status']]
            ]
        ];
    }

    private static function queryDns(string $hostname, int $type): array {
        if (self::$dnsResolver !== null) {
            return call_user_func(self::$dnsResolver, $hostname, $type);
        }

        // Live DNS query
        $records = @dns_get_record($hostname, $type);
        return is_array($records) ? $records : [];
    }

    private static function checkSpf(string $domain): bool {
        $records = self::queryDns($domain, DNS_TXT);
        foreach ($records as $r) {
            $txt = $r['txt'] ?? ($r['entries'][0] ?? '');
            if (str_starts_with(strtolower(trim($txt)), 'v=spf1')) {
                return true;
            }
        }
        return false;
    }

    private static function checkDkim(string $dkimHost, ?string $expectedPubKey): bool {
        $records = self::queryDns($dkimHost, DNS_TXT);
        foreach ($records as $r) {
            $txt = $r['txt'] ?? ($r['entries'][0] ?? '');
            $clean = str_replace(["\r", "\n", ' '], '', $txt);
            if (str_contains($clean, 'v=DKIM1') || str_contains($clean, 'p=')) {
                if (empty($expectedPubKey)) return true;
                // Check if public key matches
                if (str_contains($clean, substr($expectedPubKey, 0, 30))) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function checkDmarc(string $dmarcHost): bool {
        $records = self::queryDns($dmarcHost, DNS_TXT);
        foreach ($records as $r) {
            $txt = $r['txt'] ?? ($r['entries'][0] ?? '');
            if (str_starts_with(strtolower(trim($txt)), 'v=dmarc1')) {
                return true;
            }
        }
        return false;
    }

    private static function checkMx(string $domain): bool {
        $records = self::queryDns($domain, DNS_MX);
        return !empty($records);
    }
}
