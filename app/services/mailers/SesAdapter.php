<?php
/**
 * Raptor CRM - Amazon SES Provider Adapter
 * 
 * Dispatches marketing and transactional broadcasts via Amazon Simple Email Service (SES)
 * using the high-performance SES SMTP gateway with TLS/STARTTLS authentication,
 * dedicated SES Configuration Sets, and anti-spam deliverability headers.
 */

class SesAdapter implements MailerAdapterInterface {

    private array $config;

    public function __construct(array $config = []) {
        $region = $config['region'] ?? ($_ENV['AWS_REGION'] ?? 'us-east-1');
        $defaultHost = "email-smtp.{$region}.amazonaws.com";

        $this->config = array_merge([
            'region'            => $region,
            'access_key'        => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
            'secret_key'        => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
            'configuration_set' => $config['configuration_set'] ?? ($_ENV['SES_CONFIGURATION_SET'] ?? null),
            'smtp_host'         => $config['smtp_host'] ?? ($_ENV['SES_SMTP_HOST'] ?? $defaultHost),
            'smtp_user'         => $config['smtp_user'] ?? ($_ENV['SES_SMTP_USER'] ?? ($_ENV['AWS_ACCESS_KEY_ID'] ?? '')),
            'smtp_pass'         => $config['smtp_pass'] ?? ($_ENV['SES_SMTP_PASS'] ?? ($_ENV['AWS_SECRET_ACCESS_KEY'] ?? '')),
            'smtp_port'         => (int)($config['smtp_port'] ?? ($_ENV['SES_SMTP_PORT'] ?? 587)),
            'encryption'        => $config['encryption'] ?? 'tls',
            'timeout'           => (int)($config['timeout'] ?? 15)
        ], $config);
    }

    public function getName(): string {
        return 'ses';
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function send(array $message): MailerSendResult {
        // Build dedicated SMTP configuration for Amazon SES
        $smtpConfig = [
            'host'       => $this->config['smtp_host'],
            'port'       => $this->config['smtp_port'],
            'username'   => $this->config['smtp_user'],
            'password'   => $this->config['smtp_pass'],
            'encryption' => $this->config['encryption'] ?? 'tls',
            'timeout'    => $this->config['timeout'] ?? 15
        ];

        // Attach Amazon SES Configuration Set header for tracking open/click/reputation metrics
        if (!empty($this->config['configuration_set'])) {
            $message['headers']['X-SES-CONFIGURATION-SET'] = $this->config['configuration_set'];
        }

        // Attach Amazon SES Message Tag headers
        if (!empty($message['headers']['X-Campaign-ID'])) {
            $message['headers']['X-SES-MESSAGE-TAGS'] = 'campaign_id=' . $message['headers']['X-Campaign-ID'];
        }

        if (!class_exists('SmtpAdapter')) {
            $smtpFile = defined('APPROOT') ? (APPROOT . '/services/mailers/SmtpAdapter.php') : (__DIR__ . '/SmtpAdapter.php');
            if (file_exists($smtpFile)) {
                require_once $smtpFile;
            }
        }

        $smtp = new SmtpAdapter($smtpConfig);
        return $smtp->send($message);
    }
}


