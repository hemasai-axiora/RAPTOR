<?php
/**
 * Raptor CRM - Mailer Factory
 * 
 * Instantiates the configured MailerAdapter based on MAIL_PROVIDER setting.
 */

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

        $provider = strtolower(trim($_ENV['MAIL_PROVIDER'] ?? 'smtp'));

        return match ($provider) {
            'ses' => new SesAdapter(),
            'postmark' => new PostmarkAdapter(),
            'sendgrid' => new SendGridAdapter(),
            'mailgun' => new MailgunAdapter(),
            'mock' => new MockMailerAdapter(),
            default => new SmtpAdapter()
        };
    }
}

