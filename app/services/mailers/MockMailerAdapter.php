<?php
/**
 * Raptor CRM - Mock Mailer Adapter
 * 
 * In-memory mock adapter for test harnesses, integration tests, and local development.
 * Simulates real provider responses and captures all dispatched messages.
 */

class MockMailerAdapter implements MailerAdapterInterface {

    private static array $dispatchedMessages = [];
    private static ?string $forcedBehavior = null; // null | 'transient_error' | 'hard_error'

    public function getName(): string {
        return 'mock';
    }

    public static function setForcedBehavior(?string $behavior): void {
        self::$forcedBehavior = $behavior;
    }

    public static function getDispatchedMessages(): array {
        return self::$dispatchedMessages;
    }

    public static function reset(): void {
        self::$dispatchedMessages = [];
        self::$forcedBehavior = null;
    }

    public function send(array $message): MailerSendResult {
        if (self::$forcedBehavior === 'transient_error') {
            return MailerSendResult::failure('421 4.7.0 Connection rate limit exceeded, try again later', '421', true);
        }

        if (self::$forcedBehavior === 'hard_error') {
            return MailerSendResult::failure('550 5.1.1 Requested action not taken: mailbox unavailable', '550', false);
        }

        $messageId = 'mock-' . bin2hex(random_bytes(8)) . '@mail.raptor.test';
        $message['dispatched_at'] = date('Y-m-d H:i:s');
        $message['message_id'] = $messageId;
        self::$dispatchedMessages[] = $message;

        return MailerSendResult::success($messageId);
    }
}
