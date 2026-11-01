<?php
/**
 * Raptor CRM - Campaign Sending Domain Model
 * 
 * Manages sending domains, 2048-bit DKIM keypair generation,
 * expected DNS records, and authentication states.
 */

class CampaignDomain extends Model {

    public const STATUS_UNVERIFIED = 'unverified';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';

    public function getAllDomains(): array {
        $this->query('SELECT * FROM campaign_domains ORDER BY is_default DESC, domain_name ASC');
        return $this->resultSet() ?: [];
    }

    public function getById(int $domainId) {
        $this->query('SELECT * FROM campaign_domains WHERE domain_id = :id LIMIT 1');
        $this->bind(':id', $domainId);
        return $this->single();
    }

    public function getByName(string $domainName) {
        $clean = strtolower(trim($domainName));
        $this->query('SELECT * FROM campaign_domains WHERE domain_name = :name LIMIT 1');
        $this->bind(':name', $clean);
        return $this->single();
    }

    public function getDefaultDomain() {
        $this->query('SELECT * FROM campaign_domains WHERE is_default = 1 LIMIT 1');
        $domain = $this->single();
        if (!$domain) {
            $this->query('SELECT * FROM campaign_domains ORDER BY domain_id ASC LIMIT 1');
            $domain = $this->single();
        }
        return $domain;
    }

    /**
     * Create a new sending domain with an auto-generated 2048-bit DKIM keypair.
     */
    public function createDomain(string $domainName, string $selector = 'raptor', bool $isDefault = false): int {
        $clean = strtolower(trim($domainName));
        if (empty($clean) || !filter_var('test@' . $clean, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid sending domain format. Use a valid domain or subdomain (e.g. mail.company.com).');
        }

        // Generate 2048-bit RSA DKIM Keypair
        $keypair = self::generateDkimKeypair();

        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($isDefault) {
            $db->exec('UPDATE campaign_domains SET is_default = 0');
        }

        $expectedSpf = 'v=spf1 include:_spf.raptor.internal ~all';

        $this->query('INSERT INTO campaign_domains 
            (domain_name, is_default, spf_status, spf_record_expected, dkim_status, dkim_selector, dkim_public_key, dkim_private_key_encrypted, dmarc_status, dmarc_policy, mx_status, last_checked_at)
            VALUES 
            (:name, :is_default, "unverified", :spf, "unverified", :selector, :pub_key, :priv_key, "unverified", "none", "unverified", NULL)');

        $this->bind(':name', $clean);
        $this->bind(':is_default', $isDefault ? 1 : 0);
        $this->bind(':spf', $expectedSpf);
        $this->bind(':selector', trim($selector) ?: 'raptor');
        $this->bind(':pub_key', $keypair['public_key_clean']);
        $this->bind(':priv_key', self::encryptPrivateKey($keypair['private_key']));

        $this->execute();
        $domainId = (int)$this->lastInsertId();

        // Also initialize warm-up schedule record
        try {
            $dateToday = date('Y-m-d');
            $db->prepare('INSERT INTO campaign_warmup_schedules (domain_id, current_stage, daily_cap, sent_today, date_stamp, is_active)
                          VALUES (:did, 1, 50, 0, :today, 1)')->execute([':did' => $domainId, ':today' => $dateToday]);
        } catch (Throwable $e) {}

        return $domainId;
    }

    /**
     * Find local OpenSSL configuration file path across platforms.
     */
    private static function findOpensslConfig(): ?string {
        $candidates = [
            $_ENV['OPENSSL_CONF'] ?? null,
            getenv('OPENSSL_CONF') ?: null,
            dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
            dirname(PHP_BINARY) . '/openssl.cnf',
            'C:/Program Files/Common Files/SSL/openssl.cnf',
            'C:/xampp/apache/bin/openssl.cnf',
            'C:/wamp/bin/apache/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf'
        ];

        foreach ($candidates as $c) {
            if ($c && file_exists($c)) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Generate 2048-bit RSA Keypair for DKIM signing.
     */
    public static function generateDkimKeypair(): array {
        $cnf = self::findOpensslConfig();

        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        if ($cnf) {
            $config['config'] = $cnf;
        }

        $res = openssl_pkey_new($config);
        if ($res === false && !$cnf) {
            // Try standard fallback
            $res = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
        }

        if ($res === false) {
            $err = '';
            while ($msg = openssl_error_string()) $err .= ' ' . $msg;
            throw new RuntimeException('Failed to generate 2048-bit RSA DKIM keypair via OpenSSL.' . ($err ? " ({$err})" : ''));
        }

        $exportConfig = $cnf ? ['config' => $cnf] : null;
        openssl_pkey_export($res, $privateKey, null, $exportConfig);
        $details = openssl_pkey_get_details($res);
        $publicKey = $details['key'] ?? '';

        // Strip headers and newlines for clean DNS TXT representation
        $cleanPub = preg_replace('/^-+.*?-+/m', '', $publicKey);
        $cleanPub = str_replace(["\r", "\n", ' '], '', $cleanPub);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'public_key_clean' => $cleanPub
        ];
    }

    /**
     * Encrypt private key using APP_KEY.
     */
    private static function encryptPrivateKey(string $key): string {
        $secret = $_ENV['APP_KEY'] ?? 'raptor-campaign-crypto-key-928172';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($key, 'AES-256-CBC', substr(hash('sha256', $secret, true), 0, 32), 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt private key.
     */
    public static function decryptPrivateKey(string $encryptedPayload): ?string {
        $secret = $_ENV['APP_KEY'] ?? 'raptor-campaign-crypto-key-928172';
        $raw = base64_decode($encryptedPayload);
        if (!$raw || !str_contains($raw, '::')) return null;

        [$iv, $encrypted] = explode('::', $raw, 2);
        return openssl_decrypt($encrypted, 'AES-256-CBC', substr(hash('sha256', $secret, true), 0, 32), 0, $iv) ?: null;
    }

    /**
     * Update domain verification statuses.
     */
    public function updateStatuses(int $domainId, array $statuses): void {
        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        $sql = "UPDATE campaign_domains SET 
                    spf_status = :spf,
                    dkim_status = :dkim,
                    dmarc_status = :dmarc,
                    mx_status = :mx,
                    last_checked_at = {$nowSql}
                WHERE domain_id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':spf' => $statuses['spf_status'] ?? 'unverified',
            ':dkim' => $statuses['dkim_status'] ?? 'unverified',
            ':dmarc' => $statuses['dmarc_status'] ?? 'unverified',
            ':mx' => $statuses['mx_status'] ?? 'unverified',
            ':id' => $domainId
        ]);
    }

    public function deleteDomain(int $domainId): bool {
        $this->query('DELETE FROM campaign_domains WHERE domain_id = :id');
        $this->bind(':id', $domainId);
        return $this->execute();
    }
}
