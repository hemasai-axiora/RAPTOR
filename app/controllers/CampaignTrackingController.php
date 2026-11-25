<?php
/**
 * Raptor CRM - Public Campaign Tracking Controller
 * 
 * Handles Open Pixel requests and Signed Click-Through Redirects.
 * Public endpoint: does NOT require session authentication.
 */

class CampaignTrackingController extends Controller {

    /**
     * Handle 1x1 open tracking pixel request.
     */
    public function open() {
        $token = trim($_GET['t'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!empty($token)) {
            try {
                $tokenData = UnsubscribeService::verifyToken($token);
                $db = Database::getInstance()->getConnection();
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

                $stmt = $db->prepare('SELECT r.recipient_id, r.campaign_id, r.contact_id, r.open_count 
                                      FROM campaign_recipients r 
                                      WHERE r.unsubscribe_token = :t LIMIT 1');
                $stmt->execute([':t' => $token]);
                $recip = $stmt->fetch(PDO::FETCH_OBJ);

                $campId = $recip ? (int)$recip->campaign_id : (int)($tokenData['campaign_id'] ?? 0);
                $recipId = $recip ? (int)$recip->recipient_id : 0;
                $contactId = $recip ? (int)$recip->contact_id : (int)($tokenData['contact_id'] ?? 0);

                if ($campId > 0 && $recipId > 0) {
                    $isFirstOpen = ((int)($recip->open_count ?? 0) === 0);
                    $isMpp = TrackingService::isAppleMpp($userAgent);

                    // Update recipient record
                    $uStmt = $db->prepare("UPDATE campaign_recipients 
                                           SET open_count = open_count + 1, 
                                               opened_at = COALESCE(opened_at, {$nowSql}) 
                                           WHERE recipient_id = :rid");
                    $uStmt->execute([':rid' => $recipId]);

                    // Update campaign unique opens if first open
                    if ($isFirstOpen) {
                        $cStmt = $db->prepare('UPDATE campaigns_broadcast SET opened_count = opened_count + 1 WHERE campaign_id = :cid');
                        $cStmt->execute([':cid' => $campId]);
                    }

                    // Update contact last engagement timestamp
                    if ($contactId > 0) {
                        $cntStmt = $db->prepare("UPDATE campaign_contacts SET last_engagement_at = {$nowSql} WHERE contact_id = :cntid");
                        $cntStmt->execute([':cntid' => $contactId]);
                    }

                    // Log event
                    try {
                        $evStmt = $db->prepare('INSERT INTO campaign_email_events (campaign_id, recipient_id, contact_id, event_type, ip_address, user_agent, payload_json) 
                                               VALUES (:cid, :rid, :cntid, "open", :ip, :ua, :payload)');
                        $evStmt->execute([
                            ':cid' => $campId,
                            ':rid' => $recipId,
                            ':cntid' => $contactId,
                            ':ip' => $ip,
                            ':ua' => substr($userAgent, 0, 255),
                            ':payload' => json_encode(['first_open' => $isFirstOpen, 'is_mpp' => $isMpp])
                        ]);
                    } catch (Throwable $t) {}
                }
            } catch (Throwable $e) {
                // Silently log tracking errors so open tracking pixel never breaks
                error_log('[CampaignTrackingController:open] ' . $e->getMessage());
            }
        }

        // Return 1x1 transparent GIF with anti-caching headers
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        echo TrackingService::getTransparentGif();
        exit();
    }

    /**
     * Handle secure signed click tracking and safe redirection.
     */
    public function click() {
        $token = trim($_GET['t'] ?? '');
        $signedPayload = trim($_GET['p'] ?? '');
        $targetUrl = TrackingService::verifyUrl($signedPayload);

        // Security check: if signed target URL is invalid or forged, reject
        if (!$targetUrl) {
            http_response_code(400);
            die('Invalid or forged tracking link.');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!empty($token)) {
            try {
                $tokenData = UnsubscribeService::verifyToken($token);
                $db = Database::getInstance()->getConnection();
                $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
                $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

                $stmt = $db->prepare('SELECT r.recipient_id, r.campaign_id, r.contact_id, r.click_count 
                                      FROM campaign_recipients r 
                                      WHERE r.unsubscribe_token = :t LIMIT 1');
                $stmt->execute([':t' => $token]);
                $recip = $stmt->fetch(PDO::FETCH_OBJ);

                $campId = $recip ? (int)$recip->campaign_id : (int)($tokenData['campaign_id'] ?? 0);
                $recipId = $recip ? (int)$recip->recipient_id : 0;
                $contactId = $recip ? (int)$recip->contact_id : (int)($tokenData['contact_id'] ?? 0);

                if ($campId > 0 && $recipId > 0) {
                    $isFirstClick = ((int)($recip->click_count ?? 0) === 0);

                    // Update recipient record
                    $uStmt = $db->prepare("UPDATE campaign_recipients 
                                           SET click_count = click_count + 1, 
                                               clicked_at = COALESCE(clicked_at, {$nowSql}) 
                                           WHERE recipient_id = :rid");
                    $uStmt->execute([':rid' => $recipId]);

                    // Update campaign unique clicks if first click
                    if ($isFirstClick) {
                        $cStmt = $db->prepare('UPDATE campaigns_broadcast SET clicked_count = clicked_count + 1 WHERE campaign_id = :cid');
                        $cStmt->execute([':cid' => $campId]);
                    }

                    // Update contact last engagement timestamp
                    if ($contactId > 0) {
                        $cntStmt = $db->prepare("UPDATE campaign_contacts SET last_engagement_at = {$nowSql} WHERE contact_id = :cntid");
                        $cntStmt->execute([':cntid' => $contactId]);
                    }

                    // Log click event
                    try {
                        $evStmt = $db->prepare('INSERT INTO campaign_email_events (campaign_id, recipient_id, contact_id, event_type, ip_address, user_agent, target_url, payload_json) 
                                               VALUES (:cid, :rid, :cntid, "click", :ip, :ua, :url, :payload)');
                        $evStmt->execute([
                            ':cid' => $campId,
                            ':rid' => $recipId,
                            ':cntid' => $contactId,
                            ':ip' => $ip,
                            ':ua' => substr($userAgent, 0, 255),
                            ':url' => $targetUrl,
                            ':payload' => json_encode(['first_click' => $isFirstClick])
                        ]);
                    } catch (Throwable $t) {}
                }
            } catch (Throwable $e) {
                // Silently log error so recipient redirect is NEVER interrupted
                error_log('[CampaignTrackingController:click] ' . $e->getMessage());
            }
        }

        // Issue 302 safe redirect
        header('Location: ' . $targetUrl, true, 302);
        exit();
    }
}

