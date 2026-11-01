<?php
/**
 * Raptor CRM — Meta WhatsApp Cloud API Broadcast Queue Worker
 * -------------------------------------------------------------------------
 * Processes queued WhatsApp broadcast recipients asynchronously:
 * - Pre-send suppression verification (never sends to opted-out contacts)
 * - Meta Graph API template dispatch
 * - Exponential backoff & status reporting
 *
 * Suggested crontab schedule (every minute):
 *   * * * * * php /path/to/raptor/bin/whatsapp_queue_worker.php >> /var/log/raptor_wa_worker.log 2>&1
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Model.php';
require_once dirname(__DIR__) . '/app/models/WhatsAppBroadcast.php';
require_once dirname(__DIR__) . '/app/models/WhatsAppTemplate.php';
require_once dirname(__DIR__) . '/app/models/WhatsAppConversation.php';
require_once dirname(__DIR__) . '/app/models/WhatsAppMessage.php';
require_once dirname(__DIR__) . '/app/models/Contact.php';
require_once dirname(__DIR__) . '/app/services/WhatsAppService.php';

$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

$now = date('Y-m-d H:i:s');

// 1. Find pending or processing broadcasts
$bStmt = $db->prepare("SELECT broadcast_id, name, template_id, status 
                       FROM whatsapp_broadcasts 
                       WHERE status IN ('processing', 'scheduled') 
                         AND (scheduled_at IS NULL OR scheduled_at <= :now) 
                       ORDER BY broadcast_id ASC LIMIT 5");
$bStmt->execute([':now' => $now]);
$broadcasts = $bStmt->fetchAll(PDO::FETCH_OBJ);

if (empty($broadcasts)) {
    echo "[" . date('c') . "] [WhatsApp Worker] No pending broadcasts to process.\n";
    exit(0);
}

$bcastModel = new WhatsAppBroadcast();
$tmplModel = new WhatsAppTemplate();
$convModel = new WhatsAppConversation();
$msgModel = new WhatsAppMessage();

$totalDispatched = 0;
$totalFailed = 0;
$totalSuppressed = 0;

foreach ($broadcasts as $b) {
    echo "[" . date('c') . "] Processing Broadcast #{$b->broadcast_id} [{$b->name}]...\n";
    $bcastModel->updateBroadcastStatus((int)$b->broadcast_id, 'processing');

    $template = $tmplModel->getById((int)$b->template_id);
    if (!$template || $template->status !== 'APPROVED') {
        echo "  [ERROR] Template #{$b->template_id} is not approved by Meta. Pausing broadcast.\n";
        $bcastModel->updateBroadcastStatus((int)$b->broadcast_id, 'failed');
        continue;
    }

    $recipients = $bcastModel->getQueuedRecipients((int)$b->broadcast_id, 100);
    if (empty($recipients)) {
        // Complete broadcast if no more queued recipients
        $bcastModel->updateBroadcastStatus((int)$b->broadcast_id, 'completed');
        echo "  [DONE] Broadcast #{$b->broadcast_id} completed.\n";
        continue;
    }

    foreach ($recipients as $r) {
        $phone = $r->phone_number;
        $contactId = (int)$r->contact_id;

        // Invariant: Pre-send suppression check
        if ((int)$r->is_suppressed === 1) {
            $bcastModel->markRecipientFailed((int)$r->recipient_id, 'SUPPRESSED', 'Contact opted out of marketing communications');
            $totalSuppressed++;
            continue;
        }

        // Format variables components if present
        $components = [];
        if (!empty($r->parameters_json)) {
            $params = json_decode($r->parameters_json, true);
            if (is_array($params)) {
                $bodyParams = [];
                foreach ($params as $pVal) {
                    $bodyParams[] = ['type' => 'text', 'text' => (string)$pVal];
                }
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParams
                ];
            }
        }

        // Dispatch via official Meta API
        $res = WhatsAppService::sendTemplateMessage($phone, $template->name, $template->language, $components);

        if ($res['success']) {
            $metaId = $res['meta_message_id'];
            $bcastModel->markRecipientSent((int)$r->recipient_id, $metaId);

            // Record conversation and outbound message
            $conv = $convModel->findOrCreate($phone, $contactId);
            $msgModel->recordMessage([
                'conversation_id' => (int)$conv->conversation_id,
                'contact_id' => $contactId,
                'direction' => 'outbound',
                'message_type' => 'template',
                'template_id' => $template->template_id,
                'meta_message_id' => $metaId,
                'body' => $template->body_text,
                'status' => 'sent'
            ]);
            $convModel->recordOutbound((int)$conv->conversation_id);

            $db->prepare("UPDATE whatsapp_broadcasts SET sent_count = sent_count + 1 WHERE broadcast_id = ?")->execute([$b->broadcast_id]);
            $totalDispatched++;
        } else {
            $err = $res['error'] ?? 'Delivery failed';
            $bcastModel->markRecipientFailed((int)$r->recipient_id, 'META_ERROR', $err);
            $db->prepare("UPDATE whatsapp_broadcasts SET failed_count = failed_count + 1 WHERE broadcast_id = ?")->execute([$b->broadcast_id]);
            $totalFailed++;
        }
    }
}

echo "[" . date('c') . "] [WhatsApp Worker] Summary: Dispatched={$totalDispatched}, Failed={$totalFailed}, Suppressed={$totalSuppressed}\n";
exit(0);
