<?php
/**
 * WhatsAppWebhookController — Official Meta Webhook Receiver
 * -------------------------------------------------------------------------
 * Handles:
 * 1. Meta Webhook Verification handshake (GET)
 * 2. Inbound WhatsApp messages & 24-hr session window initialization (POST)
 * 3. Status delivery receipts (sent, delivered, read, failed)
 * 4. Automatic opt-out handling (STOP / UNSUBSCRIBE keywords)
 * 5. Customer 360 identity resolution & timeline activity logging
 */

class WhatsAppWebhookController extends Controller {

    private $conversationModel;
    private $messageModel;
    private $contactModel;

    public function __construct() {
        // Public API controller: do NOT enforce session authentication
        $this->conversationModel = $this->model('WhatsAppConversation');
        $this->messageModel = $this->model('WhatsAppMessage');
        $this->contactModel = $this->model('Contact');
    }

    public function index() {
        // 1. Meta Webhook Handshake Verification (GET)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $mode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? null);
            $token = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? null);
            $challenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? null);

            $verifiedChallenge = WhatsAppService::verifyWebhookSubscription($mode, $token, $challenge);
            if ($verifiedChallenge !== null) {
                http_response_code(200);
                header('Content-Type: text/plain');
                echo $verifiedChallenge;
                exit();
            }

            http_response_code(403);
            echo "Forbidden: Invalid verify token";
            exit();
        }

        // 2. Inbound Notifications Processing (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawPayload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;

            // In production with APP_SECRET configured, verify signature
            if (!empty($_ENV['WHATSAPP_APP_SECRET']) && $signature) {
                if (!WhatsAppService::verifyWebhookSignature($rawPayload, $signature)) {
                    http_response_code(401);
                    echo "Unauthorized: Signature mismatch";
                    exit();
                }
            }

            $data = json_decode($rawPayload, true);
            if (!is_array($data) || empty($data['entry'])) {
                http_response_code(200);
                echo "EVENT_RECEIVED";
                exit();
            }

            foreach ($data['entry'] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (($change['field'] ?? '') !== 'messages') continue;
                    $value = $change['value'] ?? [];

                    // Handle Status Receipts (sent, delivered, read, failed)
                    if (!empty($value['statuses'])) {
                        $this->processStatuses($value['statuses']);
                    }

                    // Handle Inbound Messages
                    if (!empty($value['messages'])) {
                        $this->processInboundMessages($value['messages'], $value['contacts'] ?? []);
                    }
                }
            }

            http_response_code(200);
            echo "EVENT_RECEIVED";
            exit();
        }

        http_response_code(405);
        echo "Method Not Allowed";
    }

    private function processStatuses(array $statuses): void {
        foreach ($statuses as $st) {
            $metaId = $st['id'] ?? '';
            $status = $st['status'] ?? ''; // sent, delivered, read, failed
            $timestamp = $st['timestamp'] ?? null;

            $errorCode = null;
            $errorMsg = null;
            if (!empty($st['errors'][0])) {
                $errorCode = (string)($st['errors'][0]['code'] ?? '');
                $errorMsg = (string)($st['errors'][0]['title'] ?? ($st['errors'][0]['message'] ?? ''));
            }

            if (!empty($metaId) && !empty($status)) {
                $this->messageModel->updateDeliveryStatus($metaId, $status, $timestamp, $errorCode, $errorMsg);
            }
        }
    }

    private function processInboundMessages(array $messages, array $profileContacts): void {
        // Build sender profile name lookup
        $profileNames = [];
        foreach ($profileContacts as $pc) {
            $waId = $pc['wa_id'] ?? '';
            $pName = $pc['profile']['name'] ?? '';
            if ($waId && $pName) {
                $profileNames[$waId] = $pName;
            }
        }

        foreach ($messages as $msg) {
            $senderPhone = $msg['from'] ?? '';
            $metaMsgId = $msg['id'] ?? '';
            $msgType = $msg['type'] ?? 'text';
            $body = '';

            if ($msgType === 'text') {
                $body = $msg['text']['body'] ?? '';
            } elseif ($msgType === 'interactive') {
                $body = $msg['interactive']['button_reply']['title'] ?? ($msg['interactive']['list_reply']['title'] ?? 'Button Click');
            } else {
                $body = "[{$msgType} message]";
            }

            if (empty($senderPhone)) continue;

            $senderName = $profileNames[$senderPhone] ?? '';

            // 1. Resolve or create Customer 360 Contact
            $contact = $this->contactModel->findOrCreateByIdentity('whatsapp', $senderPhone, [
                'first_name' => $senderName,
                'phone' => '+' . $senderPhone,
                'source' => 'whatsapp_inbound'
            ]);

            $contactId = (int)$contact->contact_id;

            // 2. Opt-out Keyword Detection (STOP / UNSUBSCRIBE / CANCEL)
            $cleanText = strtoupper(trim($body));
            if (in_array($cleanText, ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'OPT OUT', 'OPTOUT'], true)) {
                $this->contactModel->suppressContact($contactId, 'whatsapp_optout');
                // Automatically reply acknowledging opt-out
                WhatsAppService::sendTextMessage($senderPhone, "You have successfully unsubscribed. You will not receive further marketing messages from us. Reply START to resubscribe.");
            }

            // 3. Find or Create WhatsApp Conversation (Opens/Refreshes 24-hr session)
            $conv = $this->conversationModel->findOrCreate($senderPhone, $contactId);
            $this->conversationModel->recordInbound((int)$conv->conversation_id);

            // 4. Record Message in Ledger
            $this->messageModel->recordMessage([
                'conversation_id' => (int)$conv->conversation_id,
                'contact_id' => $contactId,
                'direction' => 'inbound',
                'message_type' => $msgType,
                'meta_message_id' => $metaMsgId,
                'body' => $body,
                'status' => 'delivered'
            ]);

            // 5. Log Omnichannel Activity to Customer 360 Timeline
            $this->contactModel->recordActivity(
                $contactId,
                'whatsapp',
                'whatsapp_received',
                'Received Inbound WhatsApp Message',
                $body,
                ['phone' => $senderPhone, 'meta_id' => $metaMsgId]
            );
        }
    }
}
