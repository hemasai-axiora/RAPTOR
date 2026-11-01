<?php
/**
 * Raptor CRM - Mailer Send Result Value Object
 */

class MailerSendResult {

    private bool $success;
    private ?string $messageId;
    private ?string $errorCode;
    private ?string $errorMessage;
    private bool $isTransient;

    public function __construct(
        bool $success,
        ?string $messageId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        bool $isTransient = false
    ) {
        $this->success = $success;
        $this->messageId = $messageId;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->isTransient = $isTransient;
    }

    public static function success(string $messageId): self {
        return new self(true, $messageId, null, null, false);
    }

    public static function failure(string $errorMessage, ?string $errorCode = null, bool $isTransient = false): self {
        return new self(false, null, $errorCode, $errorMessage, $isTransient);
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getMessageId(): ?string {
        return $this->messageId;
    }

    public function getErrorCode(): ?string {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string {
        return $this->errorMessage;
    }

    public function isTransient(): bool {
        return $this->isTransient;
    }
}

