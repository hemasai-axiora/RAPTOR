<?php
/**
 * WhatsAppService — Official Meta WhatsApp Business Cloud API Client (v21.0)
 * --------------------------------------------------------------------------
 * Integrates directly with the official Meta Graph API:
 * - Direct HTTPS requests to https://graph.facebook.com/v21.0/{phone_number_id}/messages
 * - Message templates (Marketing, Utility, Auth) with variable interpolation
 * - Free-form session messaging inside active 24-hour customer service windows
 * - Webhook challenge validation & cryptographic signature verification
 * - Mockable transport for deterministic testing without live Meta API credentials
 */

class WhatsAppService {

    private static $mockHandler = null;

    public static function setMockHandler(?callable $handler): void {
        self::$mockHandler = $handler;
    }

    private static function getApiVersion(): string {
        return $_ENV['WHATSAPP_API_VERSION'] ?? 'v21.0';
    }

    private static function getAccessToken(): string {
        return $_ENV['WHATSAPP_TOKEN'] ?? ($_ENV['WHATSAPP_ACCESS_TOKEN'] ?? '');
    }

    private static function getPhoneNumberId(): string {
        return $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '';
    }

    public static function getWebhookVerifyToken(): string {
        return $_ENV['WHATSAPP_WEBHOOK_VERIFY_TOKEN'] ?? 'raptor_wa_verify_token_9988';
    }

    private static function getAppSecret(): string {
        return $_ENV['WHATSAPP_APP_SECRET'] ?? ($_ENV['APP_SECRET'] ?? 'raptor_wa_secret_key');
    }

    /**
     * Normalize a phone number to standard Meta recipient format (E.164 without leading plus).
     * e.g. "+91 98765-43210" -> "919876543210"
     */
    public static function formatPhoneNumber(string $phone): string {
        $clean = preg_replace('/[^\d]/', '', $phone);
        return ltrim($clean, '0');
    }

    /**
     * Send a free-form text message (Permitted within the 24-hour customer service window).
     */
    public static function sendTextMessage(string $toPhone, string $text): array {
        $cleanPhone = self::formatPhoneNumber($toPhone);
        if (empty($cleanPhone) || empty(trim($text))) {
            return ['success' => false, 'error' => 'Recipient phone and message body are required'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => trim($text)
            ]
        ];

        return self::dispatchRequest('messages', $payload);
    }

    /**
     * Send an approved WhatsApp Message Template (Can be sent outside 24-hr session).
     */
    public static function sendTemplateMessage(string $toPhone, string $templateName, string $languageCode = 'en_US', array $components = []): array {
        $cleanPhone = self::formatPhoneNumber($toPhone);
        if (empty($cleanPhone) || empty(trim($templateName))) {
            return ['success' => false, 'error' => 'Recipient phone and template name are required'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'template',
            'template' => [
                'name' => trim($templateName),
                'language' => [
                    'code' => $languageCode
                ]
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return self::dispatchRequest('messages', $payload);
    }

    /**
     * Send a media message (Image, Document, Audio, Video).
     */
    public static function sendMediaMessage(string $toPhone, string $mediaType, string $mediaUrl, ?string $caption = null): array {
        $cleanPhone = self::formatPhoneNumber($toPhone);
        $mediaType = strtolower(trim($mediaType));
        if (!in_array($mediaType, ['image', 'document', 'audio', 'video'], true)) {
            return ['success' => false, 'error' => "Unsupported media type [{$mediaType}]"];
        }

        $mediaObj = ['link' => $mediaUrl];
        if ($caption && in_array($mediaType, ['image', 'document', 'video'], true)) {
            $mediaObj['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => $mediaType,
            $mediaType => $mediaObj
        ];

        return self::dispatchRequest('messages', $payload);
    }

    /**
     * Validate Meta Webhook Subscription Challenge during setup.
     */
    public static function verifyWebhookSubscription(?string $mode, ?string $token, ?string $challenge): ?string {
        if ($mode === 'subscribe' && !empty($token) && hash_equals(self::getWebhookVerifyToken(), $token)) {
            return $challenge;
        }
        return null;
    }

    /**
     * Cryptographically verify Meta X-Hub-Signature-256 payload signature.
     */
    public static function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool {
        if (empty($signatureHeader) || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $expectedHash = substr($signatureHeader, 7);
        $calculatedHash = hash_hmac('sha256', $rawPayload, self::getAppSecret());
        return hash_equals($calculatedHash, $expectedHash);
    }

    /**
     * Internal request dispatcher to Meta Graph API.
     */
    private static function dispatchRequest(string $endpoint, array $payload): array {
        // If mock handler registered (unit/regression tests), delegate to it
        if (is_callable(self::$mockHandler)) {
            return call_user_func(self::$mockHandler, $endpoint, $payload);
        }

        $token = self::getAccessToken();
        $phoneId = self::getPhoneNumberId();
        $version = self::getApiVersion();

        if (empty($token) || empty($phoneId)) {
            return [
                'success' => false,
                'error' => 'WhatsApp Cloud API credentials not configured (WHATSAPP_TOKEN or WHATSAPP_PHONE_NUMBER_ID missing)'
            ];
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneId}/{$endpoint}";
        $jsonPayload = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => "Network error: {$curlError}"];
        }

        $body = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($body['messages'][0]['id'])) {
            return [
                'success' => true,
                'meta_message_id' => $body['messages'][0]['id'],
                'response' => $body
            ];
        }

        $errMsg = $body['error']['message'] ?? ("Meta API returned HTTP {$httpCode}: " . substr($response, 0, 200));
        return [
            'success' => false,
            'error' => $errMsg,
            'response' => $body
        ];
    }
}
