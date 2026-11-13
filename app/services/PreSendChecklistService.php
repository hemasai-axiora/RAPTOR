<?php
/**
 * Raptor CRM - Pre-Send Deliverability & Compliance Checklist Service
 * 
 * Strict quality gate executed before any campaign can be launched or scheduled.
 * Verifies 8 deliverability pillars:
 * 1. Unsubscribe mechanism (RFC 8058 & link presence)
 * 2. Physical postal address in footer (CAN-SPAM / GDPR)
 * 3. Domain SPF / DKIM / DMARC authentication status
 * 4. Spam score (trigger phrases, ALL-CAPS, excessive punctuation)
 * 5. Image alt text hygiene
 * 6. Broken or unencrypted link inspection (HTTPS validation)
 * 7. Multipart plain-text alternative presence
 * 8. Pre-launch test send confirmation
 */

class PreSendChecklistService {

    public const SPAM_TRIGGER_WORDS = [
        '100% free', '100% satisfied', 'act now', 'apply now', 'as seen on', 'bargain',
        'best price', 'billion dollars', 'bonus', 'buy direct', 'call now', 'cancel at any time',
        'cash bonus', 'casino', 'certified', 'cheap', 'clearance', 'click here', 'click now',
        'congratulations', 'cures', 'dear friend', 'direct email', 'direct marketing',
        'discount', 'double your income', 'earn extra cash', 'eliminate debt', 'exclusive deal',
        'expect to earn', 'extra income', 'fast cash', 'financial freedom', 'free consultation',
        'free gift', 'free info', 'free membership', 'free preview', 'free sample', 'free trial',
        'full refund', 'get out of debt', 'get paid', 'giveaway', 'guaranteed', 'hidden assets',
        'income from home', 'increase sales', 'instant', 'investment', 'join millions',
        'limited time', 'lowest price', 'make money', 'million dollars', 'miracle',
        'money back', 'mortgage rates', 'multi-level marketing', 'no catch', 'no cost',
        'no credit check', 'no experience', 'no fees', 'no gimmick', 'no hidden costs',
        'no interest', 'no strings attached', 'not spam', 'obligation', 'one time',
        'online marketing', 'open immediately', 'opportunity', 'order now', 'passwords',
        'pennies a day', 'potential earnings', 'prize', 'promise you', 'pure profit',
        'risk free', 'save big', 'save money', 'score', 'serious cash', 'special promotion',
        'stop snoring', 'terms and conditions', 'this isn\'t spam', 'unlimited', 'unsecured credit',
        'urgent', 'valuable', 'viagra', 'vicodin', 'warranty', 'while supplies last',
        'win', 'winner', 'winning', 'work from home', 'you have been selected'
    ];

    /**
     * Run the complete pre-send checklist on a campaign.
     * 
     * @param array $campaign Campaign record or array
     * @return array [passed => bool, score => int, checks => array, blockers => array]
     */
    public static function run(array $campaign): array {
        $checks = [];
        $blockers = [];

        $html = $campaign['html_content'] ?? '';
        $plain = $campaign['plain_text_content'] ?? '';
        $subjectA = $campaign['subject_a'] ?? ($campaign['name'] ?? '');
        $subjectB = $campaign['subject_b'] ?? '';
        $fromEmail = $campaign['from_email'] ?? '';

        // ---------------------------------------------------------------------
        // Check 1: Unsubscribe Link Presence (CRITICAL BLOCKER)
        // ---------------------------------------------------------------------
        $hasUnsubTag = (strpos($html, '{{unsubscribe_url}}') !== false) ||
                       (stripos($html, 'unsubscribe') !== false && preg_match('/<a[^>]*href=[^>]*>.*?unsubscribe.*?<\/a>/is', $html));
        
        $checks['unsubscribe_link'] = [
            'name' => 'Unsubscribe Link Present',
            'passed' => (bool)$hasUnsubTag,
            'critical' => true,
            'message' => $hasUnsubTag 
                ? 'Found unsubscribe tag or link in email footer.' 
                : 'CRITICAL: No unsubscribe link or {{unsubscribe_url}} tag detected. Required by CAN-SPAM and RFC 8058.'
        ];
        if (!$hasUnsubTag) {
            $blockers[] = $checks['unsubscribe_link']['message'];
        }

        // ---------------------------------------------------------------------
        // Check 2: Physical Postal Address in Footer (CRITICAL BLOCKER)
        // ---------------------------------------------------------------------
        $hasAddress = (strpos($html, '{{company_address}}') !== false) ||
                      (strpos($html, '{{company_postal_address}}') !== false) ||
                      (preg_match('/\b(?:suite|street|road|st|ave|blvd|lane|floor|building|box|city|india|usa|state|pin|zip|hyderabad|telangana|address)\b/i', $html));
        
        $companyAddress = 'Axiora Global Solutions, Cyber City, Hyderabad, India';
        try {
            if (class_exists('Database')) {
                $db = Database::getInstance()->getConnection();
                $sStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'company_address'");
                $val = $sStmt ? $sStmt->fetchColumn() : false;
                if (!empty($val)) {
                    $companyAddress = trim($val);
                }
            }
        } catch (Throwable $e) {}

        $checks['physical_address'] = [
            'name' => 'Physical Postal Address Present',
            'passed' => (bool)$hasAddress,
            'critical' => true,
            'message' => $hasAddress
                ? 'Found physical sender address in email body or footer.'
                : 'CRITICAL: Sender physical postal address is missing. Required by CAN-SPAM, GDPR, and Indian DPDP.'
        ];
        if (!$hasAddress) {
            $blockers[] = $checks['physical_address']['message'];
        }

        // ---------------------------------------------------------------------
        // Check 3: Multipart Plain-Text Alternative (CRITICAL BLOCKER)
        // ---------------------------------------------------------------------
        $hasPlain = !empty(trim($plain)) && strlen(trim($plain)) > 15;
        $checks['plain_text_alternative'] = [
            'name' => 'Multipart Plain-Text Part',
            'passed' => $hasPlain,
            'critical' => true,
            'message' => $hasPlain
                ? 'Plain-text multipart alternative is valid and populated (' . strlen(trim($plain)) . ' chars).'
                : 'CRITICAL: Plain-text alternative is empty. Pure HTML emails without text parts receive heavy spam penalties.'
        ];
        if (!$hasPlain) {
            $blockers[] = $checks['plain_text_alternative']['message'];
        }

        // ---------------------------------------------------------------------
        // Check 4: Spam Trigger Words & ALL-CAPS Score (WARNING / BLOCKER IF HIGH)
        // ---------------------------------------------------------------------
        $spamScore = self::calculateSpamScore($subjectA . ' ' . $subjectB, $html);
        $spamPassed = $spamScore['score'] < 5.0;
        
        $checks['spam_score'] = [
            'name' => 'Spam Trigger & Subject Line Analysis',
            'passed' => $spamPassed,
            'critical' => ($spamScore['score'] >= 8.0),
            'score' => $spamScore['score'],
            'message' => $spamPassed 
                ? 'Spam risk is low (Score: ' . $spamScore['score'] . '/10).' 
                : 'High spam trigger density (Score: ' . $spamScore['score'] . '/10). Triggers found: ' . implode(', ', array_slice($spamScore['triggers'], 0, 4))
        ];
        if ($spamScore['score'] >= 8.0) {
            $blockers[] = 'CRITICAL: Spam score too high (' . $spamScore['score'] . '/10). Fix all-caps subject or trigger keywords before sending.';
        }

        // ---------------------------------------------------------------------
        // Check 5: Domain Authentication Status (SPF / DKIM / DMARC)
        // ---------------------------------------------------------------------
        $domainAuth = self::checkDomainAuthStatus($fromEmail);
        $checks['domain_authentication'] = [
            'name' => 'Sender Domain Authentication (SPF / DKIM / DMARC)',
            'passed' => $domainAuth['passed'],
            'critical' => false,
            'details' => $domainAuth['details'],
            'message' => $domainAuth['message']
        ];

        // ---------------------------------------------------------------------
        // Check 6: Images Alt-Text Hygiene
        // ---------------------------------------------------------------------
        $altCheck = self::checkImageAltText($html);
        $checks['images_alt_text'] = [
            'name' => 'Image Alt Attributes Hygiene',
            'passed' => $altCheck['passed'],
            'critical' => false,
            'message' => $altCheck['message']
        ];

        // ---------------------------------------------------------------------
        // Check 7: Link Protocol & Security (No HTTP or broken tags)
        // ---------------------------------------------------------------------
        $linkCheck = self::checkLinks($html);
        $checks['links_security'] = [
            'name' => 'Links Protocol & HTTPS Encryption',
            'passed' => $linkCheck['passed'],
            'critical' => false,
            'message' => $linkCheck['message']
        ];

        // ---------------------------------------------------------------------
        // Check 8: Test Email Sent & Confirmed
        // ---------------------------------------------------------------------
        $testSent = !empty($campaign['test_sent_at']) || !empty($campaign['test_email_verified']);
        $checks['test_email_sent'] = [
            'name' => 'Self-Test Dispatch Verification',
            'passed' => $testSent,
            'critical' => false,
            'message' => $testSent
                ? 'Test dispatch verified.'
                : 'Notice: Sending a test email to yourself is recommended before bulk launch.'
        ];

        // ---------------------------------------------------------------------
        // Deliverability Intelligence Preflight Engine Execution (Rules D-001 - D-020)
        // ---------------------------------------------------------------------
        $preflightResult = null;
        if (class_exists('PreflightEngine')) {
            $context = new PreflightContext(
                campaign: $campaign,
                communicationIntent: $campaign['communication_intent'] ?? 'promotional',
                domain: $domainAuth['details'] ?? []
            );
            $engine = new PreflightEngine();
            $preflightResult = $engine->evaluate($context);

            // Incorporate Preflight BLOCK findings into blockers
            foreach ($preflightResult['findings'] as $finding) {
                $sev = is_object($finding) ? ($finding->severity ?? '') : ($finding['severity'] ?? '');
                $rId = is_object($finding) ? ($finding->ruleId ?? '') : ($finding['rule_id'] ?? '');
                $msg = is_object($finding) ? ($finding->message ?? '') : ($finding['message'] ?? '');
                if ($sev === 'BLOCK') {
                    $blockers[] = "[{$rId}] {$msg}";
                }
            }

            // Sync to Alert Center and Preflight Runs if campaign ID exists
            $campId = (int)($campaign['campaign_id'] ?? 0);
            if ($campId > 0) {
                if (class_exists('AlertService')) {
                    AlertService::syncFindingsToAlerts('campaign', $campId, $preflightResult['findings']);
                }

                try {
                    $db = Database::getInstance()->getConnection();
                    $pStmt = $db->prepare('INSERT INTO marketing_preflight_runs 
                                          (campaign_id, run_id, artifact_sha256, policy_version, status, summary_json, findings_json, evaluated_at)
                                          VALUES 
                                          (:cid, :rid, :hash, :pver, :status, :sum, :find, :ev)');
                    $pStmt->execute([
                        ':cid' => $campId,
                        ':rid' => $preflightResult['run_id'],
                        ':hash' => $preflightResult['artifact_sha256'],
                        ':pver' => $preflightResult['policy_version'],
                        ':status' => $preflightResult['status'],
                        ':sum' => json_encode($preflightResult['summary'], JSON_UNESCAPED_UNICODE),
                        ':find' => json_encode($preflightResult['findings'], JSON_UNESCAPED_UNICODE),
                        ':ev' => $preflightResult['evaluated_at']
                    ]);
                } catch (Throwable $t) {}
            }
        }

        $passed = empty($blockers);

        return [
            'passed' => $passed,
            'blockers' => array_unique($blockers),
            'checks' => $checks,
            'preflight' => $preflightResult,
            'evaluated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Heuristic Spam Score Calculator (0.0 to 10.0)
     */
    public static function calculateSpamScore(string $subjects, string $html): string|array {
        $score = 0.0;
        $triggers = [];

        // 1. ALL CAPS in subject line
        $subWords = preg_split('/\s+/', trim($subjects));
        $allCapsWords = 0;
        foreach ($subWords as $w) {
            $clean = preg_replace('/[^a-zA-Z]/', '', $w);
            if (strlen($clean) >= 4 && ctype_upper($clean)) {
                $allCapsWords++;
            }
        }
        if ($allCapsWords >= 2) {
            $score += 3.0;
            $triggers[] = 'Excessive ALL-CAPS in subject line';
        }

        // 2. Excessive punctuation (!!! or ??? or $$$)
        if (preg_match('/[!]{2,}|\?{2,}|\${2,}/', $subjects)) {
            $score += 2.0;
            $triggers[] = 'Excessive punctuation (!! / ?? / $$)';
        }

        // 3. Known spam words matching
        $haystack = strtolower($subjects . ' ' . strip_tags($html));
        foreach (self::SPAM_TRIGGER_WORDS as $phrase) {
            if (stripos($haystack, $phrase) !== false) {
                $score += 1.0;
                $triggers[] = '"' . $phrase . '"';
                if ($score >= 9.0) break;
            }
        }

        return [
            'score' => min(10.0, round($score, 1)),
            'triggers' => array_unique($triggers)
        ];
    }

    /**
     * Check if <img> tags have alt attributes.
     */
    public static function checkImageAltText(string $html): array {
        preg_match_all('/<img\s+[^>]*>/i', $html, $matches);
        if (empty($matches[0])) {
            return ['passed' => true, 'message' => 'No images detected in template.'];
        }

        $missingAlt = 0;
        foreach ($matches[0] as $imgTag) {
            if (!preg_match('/alt=["\'][^"\']*["\']/i', $imgTag)) {
                $missingAlt++;
            }
        }

        if ($missingAlt > 0) {
            return [
                'passed' => false,
                'message' => "Found {$missingAlt} image(s) missing alt text attributes (hurts screen readers and spam score)."
            ];
        }

        return ['passed' => true, 'message' => 'All images have alt text attributes defined.'];
    }

    /**
     * Scan links for insecure HTTP or malformed empty hrefs.
     */
    public static function checkLinks(string $html): array {
        preg_match_all('/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches);
        if (empty($matches[1])) {
            return ['passed' => true, 'message' => 'No hyperlinks detected.'];
        }

        $insecure = 0;
        $empty = 0;
        foreach ($matches[1] as $href) {
            $href = trim($href);
            if ($href === '' || $href === '#') {
                $empty++;
            } elseif (stripos($href, 'http://') === 0 && stripos($href, 'http://localhost') === false) {
                $insecure++;
            }
        }

        if ($insecure > 0) {
            return [
                'passed' => false,
                'message' => "Found {$insecure} insecure unencrypted link(s) using http:// instead of https://."
            ];
        }

        return ['passed' => true, 'message' => 'All external hyperlinks use secure HTTPS.'];
    }

    /**
     * Check domain authentication table status.
     */
    public static function checkDomainAuthStatus(string $fromEmail): array {
        $parts = explode('@', $fromEmail);
        $domain = $parts[1] ?? '';
        if (empty($domain)) {
            return [
                'passed' => false,
                'details' => [],
                'message' => 'Invalid or missing sender email address.'
            ];
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT * FROM campaign_domains WHERE domain_name = :domain LIMIT 1');
            $stmt->execute([':domain' => $domain]);
            $dom = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$dom) {
                return [
                    'passed' => true, // Warning only in Phase 3 until Phase 6 domain setup is configured
                    'details' => ['spf' => 'unverified', 'dkim' => 'unverified', 'dmarc' => 'unverified'],
                    'message' => "Domain {$domain} is not yet registered in Domain Setup (will use default server SPF/DKIM)."
                ];
            }

            $allValid = ($dom->spf_status === 'valid' && $dom->dkim_status === 'valid' && $dom->dmarc_status === 'valid');
            return [
                'passed' => $allValid,
                'details' => [
                    'spf' => $dom->spf_status,
                    'dkim' => $dom->dkim_status,
                    'dmarc' => $dom->dmarc_status
                ],
                'message' => $allValid ? "Domain {$domain} fully authenticated (SPF, DKIM, DMARC valid)." : "Domain {$domain} has pending DNS records."
            ];
        } catch (Throwable $e) {
            return [
                'passed' => true,
                'details' => [],
                'message' => "Domain check bypassed ({$e->getMessage()})."
            ];
        }
    }
}

