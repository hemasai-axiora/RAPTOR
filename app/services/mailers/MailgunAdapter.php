<?php
/**
 * Raptor CRM - Mailgun Provider Adapter
 * 
 * Dispatches campaigns via Mailgun v3 REST API.
 */

class MailgunAdapter implements MailerAdapterInterface {

    private string $apiKey;
    private string $domain;

    public function __construct(?string $apiKey = null, ?string $domain = null) {
        $this->apiKey = $apiKey ?? ($_ENV['MAILGUN_API_KEY'] ?? '');
        $this->domain = $domain ?? ($_ENV['MAILGUN_DOMAIN'] ?? '');
    }

    public function getName(): string {
        return 'mailgun';
    }

    public function send(array $message): MailerSendResult {
        if (empty($this->apiKey) || empty($this->domain)) {
            return MailerSendResult::failure('Mailgun API Key or Domain is missing in .env configuration', 'CONFIG_ERROR', false);
        }

        $postFields = [
            'from' => $message['from_name'] ? "{$message['from_name']} <{$message['from_email']}>" : $message['from_email'],
            'to' => $message['to_name'] ? "{$message['to_name']} <{$message['to']}>" : $message['to'],
            'subject' => $message['subject'],
            'html' => $message['html'],
            'text' => $message['text'] ?? strip_tags($message['html'])
        ];

        if (!empty($message['reply_to'])) {
            $postFields['h:Reply-To'] = $message['reply_to'];
        }

        if (!empty($message['headers']) && is_array($message['headers'])) {
            foreach ($message['headers'] as $hName => $hVal) {
                $postFields['h:' . $hName] = $hVal;
            }
        }

        $url = 'https://api.mailgun.net/v3/' . urlencode($this->domain) . '/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_USERPWD => 'api:' . $this->apiKey,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return MailerSendResult::failure('cURL Error: ' . $curlErr, 'CURL_ERROR', true);
        }

        $data = json_decode($response, true) ?: [];

        if ($httpCode === 200 && !empty($data['id'])) {
            return MailerSendResult::success($data['id']);
        }

        $isTransient = ($httpCode === 429 || $httpCode >= 500);
        $msg = $data['message'] ?? ("Mailgun HTTP {$httpCode}: " . $response);

        return MailerSendResult::failure($msg, (string)$httpCode, $isTransient);
    }
}

