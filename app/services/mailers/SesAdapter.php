<?php
/**
 * Raptor CRM - Amazon SES Provider Adapter
 * 
 * Dispatches via Amazon SES REST API v2 / SMTP gateway.
 */

class SesAdapter implements MailerAdapterInterface {

    private array $config;

    public function __construct(array $config = []) {
        $this->config = array_merge([
            'region' => $_ENV['AWS_REGION'] ?? 'us-east-1',
            'access_key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
            'secret_key' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
            'configuration_set' => $_ENV['SES_CONFIGURATION_SET'] ?? null,
            'smtp_host' => $_ENV['SES_SMTP_HOST'] ?? 'email-smtp.us-east-1.amazonaws.com',
            'smtp_user' => $_ENV['SES_SMTP_USER'] ?? ($_ENV['AWS_ACCESS_KEY_ID'] ?? ''),
            'smtp_pass' => $_ENV['SES_SMTP_PASS'] ?? ($_ENV['AWS_SECRET_ACCESS_KEY'] ?? ''),
            'smtp_port' => (int)($_ENV['SES_SMTP_PORT'] ?? 587)
        ], $config);
    }

    public function getName(): string {
        return 'ses';
    }

    public function send(array $message): MailerSendResult {
        // Amazon SES can be cleanly powered via dedicated SES SMTP endpoint using PHPMailer
        // or REST API. SES SMTP provides native support for 2048-bit DKIM and high throughput.
        $smtpConfig = [
            'host' => $this->config['smtp_host'],
            'port' => $this->config['smtp_port'],
            'username' => $this->config['smtp_user'],
            'password' => $this->config['smtp_pass'],
            'encryption' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
        ];

        if (!empty($this->config['configuration_set'])) {
            $message['headers']['X-SES-CONFIGURATION-SET'] = $this->config['configuration_set'];
        }

        $smtp = new SmtpAdapter($smtpConfig);
        return $smtp->send($message);
    }
}
