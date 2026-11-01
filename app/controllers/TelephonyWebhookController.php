<?php
/**
 * TelephonyWebhookController — Inbound Webhook Receiver for Cloud Telephony
 * -------------------------------------------------------------------------
 * Public endpoint handling telephony status callbacks and recordings
 * from Twilio, Exotel, and Ozonetel.
 */

class TelephonyWebhookController extends Controller {

    public function status() {
        header('Content-Type: application/json; charset=utf-8');

        $callSid = $_POST['CallSid'] ?? ($_GET['CallSid'] ?? ($_POST['call_sid'] ?? ''));
        $callStatus = strtolower(trim($_POST['CallStatus'] ?? ($_GET['CallStatus'] ?? ($_POST['status'] ?? ''))));
        $duration = (int)($_POST['CallDuration'] ?? ($_GET['CallDuration'] ?? ($_POST['duration'] ?? 0)));
        $recordingUrl = $_POST['RecordingUrl'] ?? ($_GET['RecordingUrl'] ?? null);

        if (empty($callSid)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing CallSid']);
            return;
        }

        // Map provider status to standard
        $statusMap = [
            'completed' => 'completed',
            'in-progress' => 'in_progress',
            'ringing' => 'ringing',
            'busy' => 'busy',
            'no-answer' => 'no_answer',
            'failed' => 'failed',
            'canceled' => 'failed'
        ];
        $normStatus = $statusMap[$callStatus] ?? $callStatus;

        $ok = TelephonyService::handleStatusCallback($callSid, $normStatus, $duration, $recordingUrl);

        if ($ok) {
            echo json_encode(['success' => true, 'call_sid' => $callSid, 'status' => $normStatus]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Call SID not found or already processed']);
        }
    }
}

