<?php
/**
 * ApiLeadCaptureController — Public Inbound Lead Ingestion Gateway
 * -------------------------------------------------------------------------
 * Public endpoint handling:
 * 1. Embedded Web Form submissions (`/index.php?route=api_lead_capture/form`)
 * 2. Authenticated External Webhooks (`/index.php?route=api_lead_capture/webhook`)
 *
 * Implements:
 * - Anti-spam honeypot detection & IP rate-limiting
 * - Automatic Customer 360 identity resolution & anonymous cookie stitching
 * - Automatic CRM lead creation with UTM attribution
 * - Universal timeline activity logging
 */

class ApiLeadCaptureController extends Controller {

    private $contactModel;
    private $leadModel;
    private $formModel;
    private $submissionModel;
    private $webhookModel;

    public function __construct() {
        // Public API controller: do NOT enforce session authentication
        $this->contactModel = $this->model('Contact');
        $this->leadModel = $this->model('Lead');
        $this->formModel = $this->model('MarketingForm');
        $this->submissionModel = $this->model('FormSubmission');
        $this->webhookModel = $this->model('InboundWebhook');
    }

    /**
     * Ingest embedded web form submission.
     */
    public function form() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-Raptor-Visitor');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Rate limiting: 15 submissions per 60 seconds per IP
        if (class_exists('Security') && !Security::rateLimit('form_sub:' . $ip, 15, 60)) {
            $this->jsonResponse(['success' => false, 'error' => 'Rate limit exceeded. Please wait a moment.'], 429);
            return;
        }

        // Parse input (JSON or form-urlencoded)
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $data = is_array($jsonData) ? $jsonData : $_POST;

        $formUuid = trim($data['form_uuid'] ?? ($_GET['uuid'] ?? ''));
        $form = !empty($formUuid) ? $this->formModel->getByUuid($formUuid) : null;

        // Honeypot spam check
        $honeypotVal = trim($data['_raptor_hp'] ?? ($data['website_hp'] ?? ''));
        if (!empty($honeypotVal)) {
            // Silently swallow spam without creating records
            $this->submissionModel->recordSubmission([
                'form_id' => $form ? $form->form_id : null,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'submitted_data' => $data,
                'is_spam' => 1,
                'spam_reason' => 'Honeypot field filled'
            ]);
            $this->jsonResponse(['success' => true, 'message' => 'Thank you for your submission.']);
            return;
        }

        // Extract contact attributes
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? ($data['mobile'] ?? ''));
        $firstName = trim($data['first_name'] ?? ($data['name'] ?? ''));
        $lastName = trim($data['last_name'] ?? '');
        $companyName = trim($data['company_name'] ?? ($data['company'] ?? ''));
        $notes = trim($data['notes'] ?? ($data['message'] ?? ''));

        // Split single full name field if needed
        if (empty($lastName) && !empty($firstName) && str_contains($firstName, ' ')) {
            $parts = explode(' ', $firstName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1];
        }

        if (empty($email) && empty($phone)) {
            $this->jsonResponse(['success' => false, 'error' => 'Valid email address or phone number is required.'], 422);
            return;
        }

        // UTM & Session tracking
        $cookieUid = trim($data['_rpt_uid'] ?? ($_COOKIE['_rpt_uid'] ?? ($_SERVER['HTTP_X_RAPTOR_VISITOR'] ?? '')));
        $pageUrl = trim($data['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
        $referrer = trim($data['referrer'] ?? '');
        $utmSource = trim($data['utm_source'] ?? '');
        $utmMedium = trim($data['utm_medium'] ?? '');
        $utmCampaign = trim($data['utm_campaign'] ?? '');
        $utmContent = trim($data['utm_content'] ?? '');
        $utmTerm = trim($data['utm_term'] ?? '');

        // 1. Resolve or create Customer 360 profile
        $primaryChannel = !empty($email) ? 'email' : 'phone';
        $primaryVal = !empty($email) ? $email : $phone;
        $leadSource = $form ? $form->default_lead_source : ($utmSource ?: 'web_form');
        $leadQuality = $form ? $form->default_lead_quality : 'warm';

        $contact = $this->contactModel->findOrCreateByIdentity($primaryChannel, $primaryVal, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'lifecycle_stage' => 'lead',
            'lead_quality' => $leadQuality,
            'source' => $leadSource,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'assigned_user_id' => $form ? $form->assigned_user_id : null
        ]);

        $contactId = (int)$contact->contact_id;

        // 2. Stitch anonymous visitor cookie to contact
        if (!empty($cookieUid)) {
            $this->contactModel->addIdentity($contactId, 'cookie', $cookieUid, false);
            // Update web_sessions if matching cookie exists
            $this->stitchWebSession($cookieUid, $contactId);
        }

        // 3. Create or update Lead in CRM
        $leadId = $this->leadModel->addLead([
            'first_name' => $firstName ?: $contact->first_name,
            'last_name' => $lastName ?: $contact->last_name,
            'company_name' => $companyName ?: $contact->company_name,
            'email' => $email,
            'phone' => $phone,
            'status' => 'new',
            'lead_quality' => $leadQuality,
            'lead_source' => $leadSource,
            'campaign_source' => $utmCampaign,
            'assigned_to_user_id' => $form ? $form->assigned_user_id : null,
            'notes' => $notes
        ]);

        // 4. Log omnichannel activity to Customer 360 timeline
        $formTitle = $form ? $form->name : 'Inbound Web Form';
        $this->contactModel->recordActivity(
            $contactId,
            'form',
            'form_submission',
            "Submitted Form: {$formTitle}",
            !empty($notes) ? "Message: {$notes}" : "Form submitted on page [{$pageUrl}]",
            [
                'form_id' => $form ? $form->form_id : null,
                'lead_id' => $leadId,
                'utm_source' => $utmSource,
                'utm_campaign' => $utmCampaign,
                'page_url' => $pageUrl
            ]
        );

        // 5. Save audit submission record
        $subId = $this->submissionModel->recordSubmission([
            'form_id' => $form ? $form->form_id : null,
            'contact_id' => $contactId,
            'lead_id' => $leadId,
            'visitor_cookie' => $cookieUid,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'page_url' => $pageUrl,
            'referrer' => $referrer,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
            'utm_term' => $utmTerm,
            'submitted_data' => $data
        ]);

        // Increment form metrics
        if ($form) {
            $this->formModel->incrementSubmissions((int)$form->form_id);
        }

        $successMsg = $form ? $form->success_message : 'Thank you! We have received your submission.';
        $redirectUrl = $form ? $form->redirect_url : null;

        // Check if browser expects redirect vs JSON
        if (!empty($_POST) && empty($jsonData) && !empty($redirectUrl)) {
            $this->redirect($redirectUrl);
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'message' => $successMsg,
            'redirect_url' => $redirectUrl,
            'contact_uuid' => $contact->contact_uuid,
            'lead_id' => $leadId,
            'submission_id' => $subId
        ]);
    }

    /**
     * Ingest authenticated external webhook (WordPress, Webflow, Meta Lead Ads, Zapier).
     */
    public function webhook() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        $endpointKey = trim($_GET['key'] ?? '');
        if (empty($endpointKey)) {
            $this->jsonResponse(['error' => 'Missing webhook endpoint key'], 400);
            return;
        }

        $endpoint = $this->webhookModel->getByKey($endpointKey);
        if (!$endpoint) {
            $this->jsonResponse(['error' => 'Invalid or inactive webhook endpoint'], 404);
            return;
        }

        $rawPayload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_RAPTOR_SIGNATURE'] ?? ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');

        // Verify signature if provided, or verify bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

        if (!empty($signature) || !empty($token)) {
            $checkSig = !empty($signature) ? $signature : $token;
            if (!$this->webhookModel->verifySignature($endpoint, $rawPayload, $checkSig)) {
                $this->jsonResponse(['error' => 'Cryptographic signature verification failed'], 401);
                return;
            }
        }

        $data = json_decode($rawPayload, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        // Apply field mapping if configured
        $mapped = $this->applyFieldMapping($data, $endpoint->field_mapping_json);

        $email = strtolower(trim($mapped['email'] ?? ''));
        $phone = trim($mapped['phone'] ?? '');
        $firstName = trim($mapped['first_name'] ?? '');
        $lastName = trim($mapped['last_name'] ?? '');
        $companyName = trim($mapped['company_name'] ?? '');
        $notes = trim($mapped['notes'] ?? '');

        if (empty($email) && empty($phone)) {
            $this->jsonResponse(['error' => 'Webhook payload must contain valid email or phone'], 422);
            return;
        }

        $primaryChan = !empty($email) ? 'email' : 'phone';
        $primaryVal = !empty($email) ? $email : $phone;

        // Resolve Contact
        $contact = $this->contactModel->findOrCreateByIdentity($primaryChan, $primaryVal, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'lifecycle_stage' => 'lead',
            'source' => $endpoint->default_lead_source,
            'assigned_user_id' => $endpoint->assigned_user_id
        ]);

        $contactId = (int)$contact->contact_id;

        // Create Lead
        $leadId = $this->leadModel->addLead([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'status' => 'new',
            'lead_source' => $endpoint->default_lead_source,
            'assigned_to_user_id' => $endpoint->assigned_user_id,
            'notes' => $notes
        ]);

        // Log Timeline Activity
        $this->contactModel->recordActivity(
            $contactId,
            'crm',
            'webhook_lead_ingested',
            "Inbound Lead Ingested via [{$endpoint->name}]",
            "Webhook provider [{$endpoint->provider}] delivered new prospect lead.",
            ['endpoint_id' => $endpoint->endpoint_id, 'lead_id' => $leadId]
        );

        $this->webhookModel->recordHit((int)$endpoint->endpoint_id);

        $this->jsonResponse([
            'status' => 'success',
            'message' => 'Lead captured and stitched to Customer 360',
            'contact_uuid' => $contact->contact_uuid,
            'contact_id' => $contactId,
            'lead_id' => $leadId
        ]);
    }

    private function applyFieldMapping(array $data, ?string $mappingJson): array {
        if (empty($mappingJson)) {
            return $data;
        }
        $mapping = json_decode($mappingJson, true);
        if (!is_array($mapping)) {
            return $data;
        }

        $res = $data;
        foreach ($mapping as $internalKey => $externalKey) {
            if (isset($data[$externalKey])) {
                $res[$internalKey] = $data[$externalKey];
            }
        }
        return $res;
    }

    private function stitchWebSession(string $cookieUid, int $contactId): void {
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("UPDATE web_sessions SET contact_id = ? WHERE visitor_cookie = ? AND contact_id IS NULL")
               ->execute([$contactId, $cookieUid]);
        } catch (Throwable $e) {}
    }

    private function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return substr($_SERVER['HTTP_CF_CONNECTING_IP'], 0, 45);
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return substr(trim($parts[0]), 0, 45);
        }
        return substr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45);
    }

    private function jsonResponse(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }
}
