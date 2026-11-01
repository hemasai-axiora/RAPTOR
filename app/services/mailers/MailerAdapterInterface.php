<?php
/**
 * Raptor CRM - Mailer Adapter Interface
 * 
 * Defines standard contract for all transactional/bulk email service providers
 * (Amazon SES, Postmark, SendGrid, Mailgun, SMTP via PHPMailer, Mock).
 */

interface MailerAdapterInterface {

    /**
     * Send single message through provider.
     * 
     * @param array $message Array with keys:
     *   - to: string
     *   - to_name: string|null
     *   - from_email: string
     *   - from_name: string
     *   - reply_to: string|null
     *   - subject: string
     *   - html: string
     *   - text: string
     *   - headers: array (Custom headers e.g. List-Unsubscribe, Feedback-ID, Precedence)
     *   - tags: array
     *   - metadata: array
     * @return MailerSendResult
     */
    public function send(array $message): MailerSendResult;

    /**
     * Get provider name identifier.
     */
    public function getName(): string;
}

