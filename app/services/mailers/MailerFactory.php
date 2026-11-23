<?php
/**
 * Raptor CRM - Mailer Factory
 * 
 * Instantiates the configured MailerAdapter based on MAIL_PROVIDER setting.
 */

if (!interface_exists('MailerAdapterInterface')) {
    require_once __DIR__ . '/MailerAdapterInterface.php';
}

if (!class_exists('SesAdapter')) {
    $sesFile = defined('APPROOT') ? (APPROOT . '/services/mailers/SesAdapter.php') : (__DIR__ . '/SesAdapter.php');
    if (file_exists($sesFile)) {
        require_once $sesFile;
    }
}

class MailerFactory {

    private static ?MailerAdapterInterface $overrideAdapter = null;

    public static function setAdapter(MailerAdapterInterface $adapter): void {
        self::$overrideAdapter = $adapter;
    }

    public static function reset(): void {
        self::$overrideAdapter = null;
    }

    public static function getAdapter(): MailerAdapterInterface {
        if (self::$overrideAdapter !== null) {
            return self::$overrideAdapter;
        }

        // 1. Check environment variable first
        $provider = strtolower(trim($_ENV['MAIL_PROVIDER'] ?? ''));

        // 2. If not in ENV, check marketing settings from database
        $marketingConfig = [];
        if (class_exists('EmailService')) {
            $marketingConfig = EmailService::getMarketingConfig();
            if (empty($provider) && !empty($marketingConfig['driver'])) {
                $driver = strtolower(trim($marketingConfig['driver']));
                if (in_array($driver, ['ses', 'aws_ses', 'amazon_ses'], true)) {
                    $provider = 'ses';
                } else {
                    $provider = $driver;
                }
            }
        }

        if (empty($provider)) {
            $provider = 'smtp';
        }

        return match ($provider) {
            'ses', 'aws_ses', 'amazon_ses' => self::buildSesAdapter($marketingConfig),
            'postmark' => new PostmarkAdapter(),
            'sendgrid' => new SendGridAdapter(),
            'mailgun' => new MailgunAdapter(),
            'mock' => new MockMailerAdapter(),
            default => new SmtpAdapter()
        };
    }

    public static function buildSesAdapter(array $config = []): SesAdapter {
        if (empty($config) && class_exists('EmailService')) {
            $config = EmailService::getMarketingConfig();
        }

        $region = $config['ses_region'] ?? ($_ENV['AWS_REGION'] ?? 'us-east-1');
        $sesConfig = [
            'region'            => $region,
            'smtp_host'         => $config['host'] ?: "email-smtp.{$region}.amazonaws.com",
            'smtp_user'         => $config['username'] ?? ($_ENV['SES_SMTP_USER'] ?? ($_ENV['AWS_ACCESS_KEY_ID'] ?? '')),
            'smtp_pass'         => $config['password'] ?? ($_ENV['SES_SMTP_PASS'] ?? ($_ENV['AWS_SECRET_ACCESS_KEY'] ?? '')),
            'smtp_port'         => (int)($config['port'] ?? ($_ENV['SES_SMTP_PORT'] ?? 587)),
            'encryption'        => $config['encryption'] ?? 'tls',
            'configuration_set' => $config['ses_configuration_set'] ?? ($_ENV['SES_CONFIGURATION_SET'] ?? null)
        ];

        return new SesAdapter($sesConfig);
    }
}

