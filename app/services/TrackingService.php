<?php
/**
 * Raptor CRM - Email Tracking Service
 * 
 * Generates and processes:
 * 1. Open tracking pixels (1x1 transparent GIF with Apple MPP detection)
 * 2. Click tracking with HMAC URL signatures (preventing open redirect vulnerabilities)
 * 3. Injection into campaign HTML templates during queue dispatch
 */

class TrackingService {

    private static function getSecret(): string {
        return $_ENV['APP_KEY'] ?? ($_ENV['UNSUB_SECRET'] ?? 'raptor-tracking-secret-salt-883921');
    }

    /**
     * Sign a target URL for click tracking to prevent open redirect vulnerabilities.
     */
    public static function signUrl(string $url): string {
        $cleanUrl = trim($url);
        $signature = hash_hmac('sha256', $cleanUrl, self::getSecret());
        return base64_encode(json_encode(['u' => $cleanUrl, 's' => substr($signature, 0, 16)]));
    }

    /**
     * Validate and decode a signed target URL.
     */
    public static function verifyUrl(string $signedPayload): ?string {
        $raw = @base64_decode($signedPayload);
        if (!$raw) return null;

        $data = json_decode($raw, true);
        if (!isset($data['u']) || !isset($data['s'])) return null;

        $cleanUrl = trim($data['u']);
        $expectedSig = substr(hash_hmac('sha256', $cleanUrl, self::getSecret()), 0, 16);

        if (!hash_equals($expectedSig, $data['s'])) {
            return null; // Forged signature
        }

        // Additional safeguard: only allow http:// or https:// schemes
        $parsed = parse_url($cleanUrl);
        if (empty($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return $cleanUrl;
    }

    /**
     * Build the tracking pixel URL for a recipient.
     */
    public static function buildPixelUrl(string $token): string {
        $base = defined('URLROOT') ? URLROOT : '';
        return $base . '/index.php?route=campaign_tracking/open&t=' . urlencode($token);
    }

    /**
     * Build signed click redirect URL.
     */
    public static function buildClickUrl(string $token, string $targetUrl): string {
        $base = defined('URLROOT') ? URLROOT : '';
        $signed = self::signUrl($targetUrl);
        return $base . '/index.php?route=campaign_tracking/click&t=' . urlencode($token) . '&p=' . urlencode($signed);
    }

    /**
     * Inject open tracking pixel and wrap hyperlinks with click tracking in HTML.
     */
    public static function injectTracking(string $html, string $token, bool $trackOpens = true, bool $trackClicks = true): string {
        $trackedHtml = $html;

        // 1. Wrap <a> links (excluding unsubscribe links and mailto)
        if ($trackClicks) {
            $trackedHtml = preg_replace_callback('/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/is', function($matches) use ($token) {
                $beforeHref = $matches[1];
                $url = trim($matches[2]);
                $afterHref = $matches[3];

                // Skip internal anchor jumps, unsubscribe links, mailto, tel
                if (str_starts_with($url, '#') || 
                    str_starts_with($url, 'mailto:') || 
                    str_starts_with($url, 'tel:') || 
                    stripos($url, 'unsubscribe') !== false) {
                    return $matches[0];
                }

                $clickUrl = self::buildClickUrl($token, $url);
                return '<a ' . $beforeHref . 'href="' . htmlspecialchars($clickUrl) . '"' . $afterHref . '>';
            }, $trackedHtml);
        }

        // 2. Inject 1x1 transparent open tracking pixel right before </body> or at the end
        if ($trackOpens) {
            $pixelUrl = self::buildPixelUrl($token);
            $pixelTag = '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" border="0" style="display:none;width:1px;height:1px;border:0;" alt="" />';

            if (stripos($trackedHtml, '</body>') !== false) {
                $trackedHtml = str_ireplace('</body>', $pixelTag . '</body>', $trackedHtml);
            } else {
                $trackedHtml .= $pixelTag;
            }
        }

        return $trackedHtml;
    }

    /**
     * Transparent 1x1 GIF binary string.
     */
    public static function getTransparentGif(): string {
        return base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    }

    /**
     * Check if user agent represents Apple Mail Privacy Protection (MPP proxy).
     */
    public static function isAppleMpp(?string $userAgent): bool {
        if (empty($userAgent)) return false;
        if (stripos($userAgent, 'Apple-Mail-Privacy-Proxy') !== false) return true;
        // Apple Mail Privacy Protection fetches images via cloud proxies with specific UA signatures
        return (stripos($userAgent, 'AppleWebKit') !== false && 
                (stripos($userAgent, 'Mozilla/5.0') !== false) &&
                (stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) &&
                (stripos($userAgent, 'Mobile') === false || stripos($userAgent, 'Safari') === false));
    }
}
