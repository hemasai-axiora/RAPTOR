<?php
/**
 * Raptor CRM - Postmark Provider Adapter
 * 
 * High-reputation deliverability provider via Postmark REST API.
 */

class PostmarkAdapter implements MailerAdapterInterface {

    private string $serverToken;

    public function __construct(?string $serverToken = null) {
        $this->serverToken = $serverToken ?? ($_ENV['POSTMARK_SERVER_TOKEN'] ?? '');
    }

    public function getName(): string {
        return 'postmark';
    }

    public function send(array $message): MailerSendResult {
        if (empty($this->serverToken)) {
            return MailerSendResult::failure('Postmark Server Token is missing in .env configuration', 'CONFIG_ERROR', false);
        }

        $headersList = [];
        if (!empty($message['headers']) && is_array($message['headers'])) {
            foreach ($message['headers'] as $name => $value) {
                $headersList[] = ['Name' => $name, 'Value' => $value];
            }
        }

        $payload = [
            'From' => $message['from_name'] ? "{$message['from_name']} <{$message['from_email']}>" : $message['from_email'],
            'To' => $message['to_name'] ? "{$message['to_name']} <{$message['to']}>" : $message['to'],
            'Subject' => $message['subject'],
            'HtmlBody' => $message['html'],
            'TextBody' => $message['text'] ?? strip_tags($message['html']),
            'Headers' => $headersList,
            'TrackOpens' => false,
            'TrackLinks' => 'None'
        ];

        if (!empty($message['reply_to'])) {
            $payload['ReplyTo'] = $message['reply_to'];
        }

        $ch = curl_init('https://api.postmarkapp.com/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Postmark-Server-Token: ' . $this->serverToken
            ],
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

        if ($httpCode === 200 && !empty($data['MessageID'])) {
            return MailerSendResult::success($data['MessageID']);
        }

        $msg = $data['Message'] ?? ("HTTP {$httpCode}: " . $response);
        $isTransient = ($httpCode === 429 || $httpCode >= 500);

        return MailerSendResult::failure($msg, (string)($data['ErrorCode'] ?? $httpCode), $isTransient);
    }
}
