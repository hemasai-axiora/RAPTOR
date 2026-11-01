<?php
/**
 * Raptor CRM - SendGrid Provider Adapter
 * 
 * Dispatches campaigns via SendGrid v3 Mail Send REST API.
 */

class SendGridAdapter implements MailerAdapterInterface {

    private string $apiKey;

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey ?? ($_ENV['SENDGRID_API_KEY'] ?? '');
    }

    public function getName(): string {
        return 'sendgrid';
    }

    public function send(array $message): MailerSendResult {
        if (empty($this->apiKey)) {
            return MailerSendResult::failure('SendGrid API Key is missing in .env configuration', 'CONFIG_ERROR', false);
        }

        $payload = [
            'personalizations' => [
                [
                    'to' => [
                        ['email' => $message['to'], 'name' => $message['to_name'] ?: null]
                    ],
                    'subject' => $message['subject']
                ]
            ],
            'from' => [
                'email' => $message['from_email'],
                'name' => $message['from_name'] ?: null
            ],
            'content' => [
                [
                    'type' => 'text/plain',
                    'value' => $message['text'] ?? strip_tags($message['html'])
                ],
                [
                    'type' => 'text/html',
                    'value' => $message['html']
                ]
            ]
        ];

        if (!empty($message['reply_to'])) {
            $payload['reply_to'] = ['email' => $message['reply_to']];
        }

        if (!empty($message['headers']) && is_array($message['headers'])) {
            $payload['headers'] = $message['headers'];
        }

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headersText = substr($response, 0, $headerSize);
        $bodyText = substr($response, $headerSize);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return MailerSendResult::failure('cURL Error: ' . $curlErr, 'CURL_ERROR', true);
        }

        // SendGrid returns 202 Accepted on success with X-Message-Id header
        if ($httpCode === 202 || $httpCode === 200) {
            $messageId = 'sg-' . bin2hex(random_bytes(8));
            if (preg_match('/X-Message-Id:\s*([^\r\n]+)/i', $headersText, $m)) {
                $messageId = trim($m[1]);
            }
            return MailerSendResult::success($messageId);
        }

        $isTransient = ($httpCode === 429 || $httpCode >= 500);
        return MailerSendResult::failure("SendGrid HTTP {$httpCode}: {$bodyText}", (string)$httpCode, $isTransient);
    }
}
