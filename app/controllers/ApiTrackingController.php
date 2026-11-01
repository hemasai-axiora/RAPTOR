<?php
/**
 * ApiTrackingController — Web Analytics & Visitor Stitching Collector
 * -------------------------------------------------------------------------
 * Public endpoint receiving beacons from raptor.js:
 * - Upserts anonymous sessions in `web_sessions`
 * - Stitches anonymous cookies to Customer 360 contact profiles upon identification
 * - Logs real pageview and custom web events directly to universal timeline
 */

class ApiTrackingController extends Controller {

    private $contactModel;

    public function __construct() {
        $this->contactModel = $this->model('Contact');
    }

    public function collect() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit();
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data) || empty($data['visitor_cookie'])) {
            http_response_code(400);
            exit();
        }

        $cookieUid = substr(trim($data['visitor_cookie']), 0, 64);
        $eventType = trim($data['event_type'] ?? 'pageview');
        $ip = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $db = Database::getInstance()->getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        // 1. Check if this visitor cookie is already stitched to a Contact
        $stmt = $db->prepare("SELECT contact_id FROM contact_identities WHERE channel = 'cookie' AND identity_value = :cookie LIMIT 1");
        $stmt->execute([':cookie' => $cookieUid]);
        $contactId = $stmt->fetchColumn();

        // 2. Handle 'identify' event (triggered by form submit interception)
        if ($eventType === 'identify' && (!empty($data['email']) || !empty($data['phone']))) {
            $email = strtolower(trim($data['email'] ?? ''));
            $phone = trim($data['phone'] ?? '');
            $name = trim($data['name'] ?? '');

            $primaryChan = !empty($email) ? 'email' : 'phone';
            $primaryVal = !empty($email) ? $email : $phone;

            $contact = $this->contactModel->findOrCreateByIdentity($primaryChan, $primaryVal, [
                'first_name' => $name,
                'email' => $email,
                'phone' => $phone,
                'source' => 'web_auto_capture'
            ]);

            $contactId = (int)$contact->contact_id;
            // Link cookie identity
            $this->contactModel->addIdentity($contactId, 'cookie', $cookieUid, false);

            // Log activity
            $this->contactModel->recordActivity(
                $contactId,
                'web',
                'visitor_identified',
                'Website Visitor Identified via Form',
                "Anonymous browsing session successfully linked to contact profile on URL [{$data['page_url']}].",
                ['page_url' => $data['page_url'] ?? '', 'cookie' => $cookieUid]
            );
        }

        // 3. Upsert into web_sessions
        $now = date('Y-m-d H:i:s');
        try {
            $sessStmt = $db->prepare("SELECT session_id, pageviews_count, contact_id FROM web_sessions WHERE visitor_cookie = :cookie LIMIT 1");
            $sessStmt->execute([':cookie' => $cookieUid]);
            $session = $sessStmt->fetch(PDO::FETCH_OBJ);

            if ($session) {
                $updSql = "UPDATE web_sessions SET pageviews_count = pageviews_count + 1, last_seen_at = :now";
                $updParams = [':now' => $now, ':sid' => $session->session_id];
                if (!$session->contact_id && $contactId) {
                    $updSql .= ", contact_id = :cid";
                    $updParams[':cid'] = $contactId;
                }
                $updSql .= " WHERE session_id = :sid";
                $db->prepare($updSql)->execute($updParams);
            } else {
                $insSql = "INSERT INTO web_sessions (visitor_cookie, contact_id, ip_address, user_agent, first_page_url, referrer, utm_source, utm_medium, utm_campaign, pageviews_count, first_seen_at, last_seen_at)
                           VALUES (:cookie, :cid, :ip, :ua, :url, :ref, :usrc, :umed, :ucmp, 1, :now, :now)";
                $db->prepare($insSql)->execute([
                    ':cookie' => $cookieUid,
                    ':cid' => $contactId ?: null,
                    ':ip' => $ip,
                    ':ua' => substr($userAgent, 0, 255),
                    ':url' => substr($data['page_url'] ?? '', 0, 2048),
                    ':ref' => substr($data['referrer'] ?? '', 0, 2048),
                    ':usrc' => substr($data['utm_source'] ?? '', 0, 100),
                    ':umed' => substr($data['utm_medium'] ?? '', 0, 100),
                    ':ucmp' => substr($data['utm_campaign'] ?? '', 0, 100),
                    ':now' => $now
                ]);
            }
        } catch (Throwable $e) {}

        // 4. If identified, log pageview directly to contact's universal activity timeline
        if ($contactId && $eventType === 'pageview') {
            $pageTitle = trim($data['page_title'] ?? 'Web Page');
            $pageUrl = trim($data['page_url'] ?? '');
            $this->contactModel->recordActivity(
                (int)$contactId,
                'web',
                'pageview',
                "Visited: {$pageTitle}",
                "Page URL: {$pageUrl}",
                ['url' => $pageUrl, 'title' => $pageTitle, 'utm_source' => $data['utm_source'] ?? '']
            );
        }

        http_response_code(204);
        exit();
    }

    private function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return substr($_SERVER['HTTP_CF_CONNECTING_IP'], 0, 45);
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return substr(trim($parts[0]), 0, 45);
        }
        return substr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45);
    }
}

