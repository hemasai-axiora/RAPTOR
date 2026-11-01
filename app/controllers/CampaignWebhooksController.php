<?php
/**
 * Raptor CRM - Public Webhook Endpoints Controller
 * 
 * Ingests bounce, complaint, and delivery webhooks from Amazon SES,
 * Postmark, SendGrid, and Mailgun.
 * Public endpoint: does NOT require session authentication.
 */

class CampaignWebhooksController extends Controller {

    /**
     * Amazon SES / SNS Notification Handler
     */
    public function ses() {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];

        // Handle Amazon SNS SubscriptionConfirmation handshake
        if (($data['Type'] ?? '') === 'SubscriptionConfirmation') {
            if (!empty($data['SubscribeURL'])) {
                @file_get_contents($data['SubscribeURL']);
            }
            http_response_code(200);
            echo 'SNS Subscription Confirmed';
            exit();
        }

        // Process SNS Notification
        if (($data['Type'] ?? '') === 'Notification') {
            $message = json_decode($data['Message'] ?? '{}', true) ?: [];
            $notificationType = $message['notificationType'] ?? '';

            if ($notificationType === 'Bounce') {
                $bounce = $message['bounce'] ?? [];
                $bounceType = $bounce['bounceType'] ?? 'Permanent';
                $recipients = $bounce['bouncedRecipients'] ?? [];
                foreach ($recipients as $r) {
                    WebhookProcessorService::processBounce(
                        $r['emailAddress'] ?? '',
                        $bounceType,
                        $r['diagnosticCode'] ?? ($bounce['bounceSubType'] ?? 'SES Bounce'),
                        null,
                        $message
                    );
                }
            } elseif ($notificationType === 'Complaint') {
                $complaint = $message['complaint'] ?? [];
                $recipients = $complaint['complainedRecipients'] ?? [];
                $feedbackType = $complaint['complaintFeedbackType'] ?? 'abuse';
                foreach ($recipients as $r) {
                    WebhookProcessorService::processComplaint(
                        $r['emailAddress'] ?? '',
                        $feedbackType,
                        null,
                        $message
                    );
                }
            } elseif ($notificationType === 'Delivery') {
                $delivery = $message['delivery'] ?? [];
                $recipients = $delivery['recipients'] ?? [];
                foreach ($recipients as $email) {
                    WebhookProcessorService::processDelivery($email, $delivery['smtpResponse'] ?? null);
                }
            }
        }

        http_response_code(200);
        echo 'SES Webhook Processed';
        exit();
    }

    /**
     * Postmark Webhook Handler
     */
    public function postmark() {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        $recordType = $data['RecordType'] ?? '';

        if ($recordType === 'Bounce') {
            $isHard = in_array((int)($data['TypeCode'] ?? 0), [1, 512, 1024], true);
            WebhookProcessorService::processBounce(
                $data['Email'] ?? '',
                $isHard ? 'hard' : 'soft',
                $data['Description'] ?? 'Postmark bounce',
                null,
                $data
            );
        } elseif ($recordType === 'SpamComplaint') {
            WebhookProcessorService::processComplaint(
                $data['Email'] ?? '',
                'spam_complaint',
                null,
                $data
            );
        } elseif ($recordType === 'Delivery') {
            WebhookProcessorService::processDelivery($data['Recipient'] ?? '', $data['MessageID'] ?? null);
        }

        http_response_code(200);
        echo 'Postmark Webhook Processed';
        exit();
    }

    /**
     * SendGrid Event Webhook Handler
     */
    public function sendgrid() {
        $raw = file_get_contents('php://input');
        $events = json_decode($raw, true) ?: [];

        if (is_array($events)) {
            foreach ($events as $e) {
                $event = $e['event'] ?? '';
                $email = $e['email'] ?? '';
                $campId = isset($e['camp_id']) ? (int)$e['camp_id'] : null;

                if ($event === 'bounce') {
                    WebhookProcessorService::processBounce($email, 'hard', $e['reason'] ?? 'SendGrid Bounce', $campId, $e);
                } elseif ($event === 'spamreport') {
                    WebhookProcessorService::processComplaint($email, 'spamreport', $campId, $e);
                } elseif ($event === 'delivered') {
                    WebhookProcessorService::processDelivery($email, $e['sg_message_id'] ?? null, $campId);
                }
            }
        }

        http_response_code(200);
        echo 'SendGrid Webhook Processed';
        exit();
    }

    /**
     * Mailgun Webhook Handler
     */
    public function mailgun() {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        $sig = $data['signature'] ?? [];
        if (!empty($sig['token']) && !empty($sig['timestamp']) && !empty($sig['signature'])) {
            if (!WebhookProcessorService::verifyMailgunSignature($sig['token'], (string)$sig['timestamp'], $sig['signature'])) {
                http_response_code(403);
                die('Invalid Mailgun signature');
            }
        }

        $eventData = $data['event-data'] ?? $data;
        $event = $eventData['event'] ?? '';
        $email = $eventData['recipient'] ?? '';

        if ($event === 'failed') {
            $severity = $eventData['severity'] ?? 'permanent';
            WebhookProcessorService::processBounce(
                $email,
                ($severity === 'permanent') ? 'hard' : 'soft',
                $eventData['delivery-status']['description'] ?? 'Mailgun failure',
                null,
                $eventData
            );
        } elseif ($event === 'complained') {
            WebhookProcessorService::processComplaint($email, 'complaint', null, $eventData);
        } elseif ($event === 'delivered') {
            WebhookProcessorService::processDelivery($email, $eventData['message']['headers']['message-id'] ?? null);
        }

        http_response_code(200);
        echo 'Mailgun Webhook Processed';
        exit();
    }
}
