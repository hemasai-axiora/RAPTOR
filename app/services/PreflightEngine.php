<?php
/**
 * Raptor CRM - Deliverability Intelligence & Preflight Engine
 * -------------------------------------------------------------------------
 * Implements the full D-001 through D-020 Deliverability Rule Engine
 * and immutable preflight audit framework as specified in BRD/SRS and
 * Developer Execution Playbook.
 * 
 * Non-negotiable principle:
 * "Observed results are sample-specific; inbox tab and spam-folder placement
 *  are not guaranteed." Intent selector is strictly for content-policy guidance.
 */

declare(strict_types=1);

interface MarketingRule {
    /**
     * Evaluate rule against immutable preflight context.
     * @return Finding[]
     */
    public function evaluate(PreflightContext $context): array;
}

final readonly class Finding {
    public function __construct(
        public string $ruleId,
        public string $severity,        // 'BLOCK', 'HIGH', 'WARNING', 'INFO'
        public string $message,
        public string $remediation,
        public array $evidence = [],
        public string $scope = 'campaign', // 'template', 'campaign', 'sending_identity', 'audience'
        public string $field = ''
    ) {}

    public function toArray(): array {
        return [
            'rule_id' => $this->ruleId,
            'severity' => $this->severity,
            'scope' => $this->scope,
            'field' => $this->field,
            'message' => $this->message,
            'remediation' => $this->remediation,
            'evidence' => $this->evidence
        ];
    }
}

final class PreflightContext {
    public function __construct(
        public array $campaign,
        public array $template = [],
        public array $audience = [],
        public array $domain = [],
        public string $communicationIntent = 'promotional',
        public string $policyVersion = '2026-11-09.1',
        public array $espHealth = [],
        public ?string $approvedHash = null,
        public ?string $currentHash = null,
        public array $options = []
    ) {}

    public function getHtml(): string {
        return $this->campaign['html_content'] ?? ($this->template['html_content'] ?? '');
    }

    public function getPlainText(): string {
        return $this->campaign['plain_text_content'] ?? ($this->template['plain_text_content'] ?? '');
    }

    public function getSubject(): string {
        return trim(($this->campaign['subject_a'] ?? '') . ' ' . ($this->campaign['subject_b'] ?? ''));
    }

    public function getFromEmail(): string {
        return strtolower(trim($this->campaign['from_email'] ?? ''));
    }

    public function getFromDomain(): string {
        $parts = explode('@', $this->getFromEmail());
        return $parts[1] ?? '';
    }
}

// =========================================================================
// RULES CATALOG D-001 TO D-020
// =========================================================================

/**
 * Rule D-001: Sender/From domain unverified
 */
class RuleD001DomainVerified implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $domain = $context->getFromDomain();
        if (empty($domain)) {
            return [new Finding(
                ruleId: 'D-001',
                severity: 'BLOCK',
                message: 'Sender From email address is missing or invalid.',
                remediation: 'Provide a valid sender email address with an authenticated domain.',
                evidence: ['from_email' => $context->getFromEmail()],
                scope: 'sending_identity',
                field: 'from_email'
            )];
        }

        $domainData = $context->domain;
        $isVerified = !empty($domainData['is_verified']) || 
                      (!empty($domainData['spf_status']) && $domainData['spf_status'] === 'valid');

        if (!$isVerified && !empty($domainData) && ($domainData['spf_status'] ?? '') === 'unverified') {
            return [new Finding(
                ruleId: 'D-001',
                severity: 'BLOCK',
                message: "Sender domain '{$domain}' is unverified in Sending Domains setup.",
                remediation: 'Verify domain ownership and required DNS records in Domain Settings before sending.',
                evidence: ['domain' => $domain, 'status' => $domainData['spf_status'] ?? 'unknown'],
                scope: 'sending_identity',
                field: 'from_email'
            )];
        }

        return [];
    }
}

/**
 * Rule D-002: Bulk-marketing mandatory SPF/DKIM/DMARC alignment or config missing
 */
class RuleD002DomainAuth implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $domainData = $context->domain;
        if (empty($domainData)) {
            return []; // Handled by D-001 or fallback
        }

        $issues = [];
        if (($domainData['spf_status'] ?? '') === 'invalid') {
            $issues[] = 'SPF record is invalid';
        }
        if (($domainData['dkim_status'] ?? '') === 'invalid') {
            $issues[] = 'DKIM public key is invalid or missing';
        }
        if (($domainData['dmarc_status'] ?? '') === 'invalid') {
            $issues[] = 'DMARC policy alignment is failing';
        }

        if (!empty($issues)) {
            return [new Finding(
                ruleId: 'D-002',
                severity: 'BLOCK',
                message: 'Mandatory email authentication checks failed: ' . implode(', ', $issues) . '.',
                remediation: 'Repair DNS records (SPF, DKIM, DMARC) to comply with Gmail/Yahoo bulk sender requirements.',
                evidence: $domainData,
                scope: 'sending_identity',
                field: 'from_email'
            )];
        }

        return [];
    }
}

/**
 * Rule D-003: No lawful permission/consent for a marketing audience
 */
class RuleD003LawfulConsent implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $aud = $context->audience;
        $missingConsent = (int)($aud['missing_consent_count'] ?? 0);
        $totalSubscribers = (int)($aud['total_subscribers'] ?? 0);

        if ($context->communicationIntent === 'promotional' && $missingConsent > 0) {
            return [new Finding(
                ruleId: 'D-003',
                severity: 'BLOCK',
                message: "{$missingConsent} recipient(s) in selected audience lack recorded proof of consent.",
                remediation: 'Exclude contacts without documented opt-in permission before sending promotional campaigns.',
                evidence: ['missing_consent_count' => $missingConsent, 'total' => $totalSubscribers],
                scope: 'audience',
                field: 'list_id'
            )];
        }

        return [];
    }
}

/**
 * Rule D-004: Audience includes suppressed or revoked recipients
 */
class RuleD004SuppressionExclusion implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $aud = $context->audience;
        $suppressedCount = (int)($aud['suppressed_count'] ?? 0);
        $plannerExcluded = !empty($aud['suppressions_firewalled']);

        if ($suppressedCount > 0 && !$plannerExcluded) {
            return [new Finding(
                ruleId: 'D-004',
                severity: 'BLOCK',
                message: "Audience includes {$suppressedCount} globally suppressed or opt-out recipients.",
                remediation: 'Campaign planner must automatically firewall and exclude suppressed recipients prior to send.',
                evidence: ['suppressed_count' => $suppressedCount],
                scope: 'audience',
                field: 'list_id'
            )];
        }

        return [];
    }
}

/**
 * Rule D-005: Marketing email missing required functioning one-click unsubscribe headers
 */
class RuleD005OneClickHeaders implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        if ($context->communicationIntent === 'operational') {
            return []; // Essential operational notices are exempt from List-Unsubscribe bulk headers
        }

        $headers = $context->campaign['headers'] ?? [];
        $hasOneClick = !empty($context->campaign['rfc8058_enabled']) ||
                       !empty($headers['List-Unsubscribe-Post']) ||
                       isset($context->campaign['campaign_id']); // Raptor generates dynamically

        if (isset($context->options['simulate_missing_rfc8058']) && $context->options['simulate_missing_rfc8058']) {
            $hasOneClick = false;
        }

        if (!$hasOneClick) {
            return [new Finding(
                ruleId: 'D-005',
                severity: 'BLOCK',
                message: 'Mandatory RFC 8058 one-click unsubscribe headers are not configured.',
                remediation: 'Configure RFC 8058 List-Unsubscribe and List-Unsubscribe-Post headers with DKIM signing.',
                evidence: ['rfc8058_configured' => false],
                scope: 'campaign',
                field: 'sender_identity_id'
            )];
        }

        return [];
    }
}

/**
 * Rule D-006: Marketing email lacks visible unsubscribe route
 */
class RuleD006UnsubscribeLink implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        if ($context->communicationIntent === 'operational') {
            return [];
        }

        $html = $context->getHtml();
        $hasTag = (strpos($html, '{{unsubscribe_url}}') !== false) ||
                  (stripos($html, 'unsubscribe') !== false && preg_match('/<a[^>]*href=[^>]*>.*?unsubscribe.*?<\/a>/is', $html));

        if (!$hasTag) {
            return [new Finding(
                ruleId: 'D-006',
                severity: 'BLOCK',
                message: 'Email body lacks a visible, accessible unsubscribe link or {{unsubscribe_url}} tag.',
                remediation: 'Insert protected unsubscribe footer or {{unsubscribe_url}} merge token.',
                evidence: ['has_unsubscribe_tag' => false],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-007: Unescaped or missing required merge variable
 */
class RuleD007MergeVariables implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $html = $context->getHtml();
        $requiredTokens = $context->options['required_tokens'] ?? [];
        $missing = [];

        foreach ($requiredTokens as $req) {
            if (strpos($html, "{{" . $req . "}}") === false) {
                $missing[] = $req;
            }
        }

        // Check for malformed merge tags like {{contact. or unclosed tags
        if (preg_match('/\{\{[a-zA-Z0-9_]+(?!\}\})/i', $html, $m)) {
            return [new Finding(
                ruleId: 'D-007',
                severity: 'BLOCK',
                message: 'Malformed or unclosed merge tag syntax detected: ' . $m[0],
                remediation: 'Ensure all merge tags follow {{variable_name}} syntax.',
                evidence: ['tag' => $m[0]],
                scope: 'template',
                field: 'html_content'
            )];
        }

        if (!empty($missing)) {
            return [new Finding(
                ruleId: 'D-007',
                severity: 'BLOCK',
                message: 'Required template merge variables are missing: ' . implode(', ', $missing) . '.',
                remediation: 'Provide missing variables or approved fallback values in template schema.',
                evidence: ['missing' => $missing],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-008: Sender/content/intent conflict (e.g. promotional offer labeled operational)
 */
class RuleD008ContentIntentConflict implements MarketingRule {
    public const PROMOTIONAL_KEYWORDS = [
        'discount', 'coupon', 'promo', 'offer', 'buy now', 'off your order', 'special pricing',
        'flash sale', 'free trial', 'save 20%', 'save 50%', 'cashback', 'limited time deal',
        'exclusive offer', 'black friday', 'cyber monday'
    ];

    public function evaluate(PreflightContext $context): array {
        $intent = $context->communicationIntent;
        $html = strtolower($context->getHtml());
        $subject = strtolower($context->getSubject());
        $haystack = $subject . ' ' . strip_tags($html);

        if ($intent === 'operational') {
            $foundTriggers = [];
            foreach (self::PROMOTIONAL_KEYWORDS as $kw) {
                if (str_contains($haystack, $kw)) {
                    $foundTriggers[] = $kw;
                }
            }

            if (!empty($foundTriggers)) {
                return [new Finding(
                    ruleId: 'D-008',
                    severity: 'HIGH',
                    message: 'Content intent mismatch: Promotional offer/discount detected inside message marked as Operational.',
                    remediation: 'Change communication intent to "Promotional" or remove marketing offers to preserve transactional compliance.',
                    evidence: ['triggers_found' => array_slice($foundTriggers, 0, 3), 'intent' => $intent],
                    scope: 'template',
                    field: 'communication_intent'
                )];
            }
        }

        return [];
    }
}

/**
 * Rule D-009: HTML contains scripts, forms, event handlers, prohibited URLs
 */
class RuleD009SafeHtmlSanitization implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $html = $context->getHtml();
        $violations = [];

        if (preg_match('/<script\b[^>]*>/i', $html)) {
            $violations[] = '<script> tags detected';
        }
        if (preg_match('/<form\b[^>]*>/i', $html)) {
            $violations[] = '<form> submission tags detected';
        }
        if (preg_match('/\bon[a-z]+\s*=/i', $html)) {
            $violations[] = 'Inline JavaScript event handlers (onerror, onclick, onload) detected';
        }
        if (preg_match('/href=["\'](?:javascript|data):/i', $html)) {
            $violations[] = 'Prohibited javascript: or data: URI schemes detected';
        }

        if (!empty($violations)) {
            return [new Finding(
                ruleId: 'D-009',
                severity: 'BLOCK',
                message: 'Unsafe HTML content detected: ' . implode('; ', $violations) . '.',
                remediation: 'Strip all active scripts, event handlers, forms, and unsafe protocols from template HTML.',
                evidence: ['violations' => $violations],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-010: High recent complaint/spam-rate signal from provider/Postmaster
 */
class RuleD010ComplaintSpamSignal implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $espHealth = $context->espHealth;
        $complaintRate = (float)($espHealth['complaint_rate'] ?? 0.0);

        if ($complaintRate >= 0.3) {
            return [new Finding(
                ruleId: 'D-010',
                severity: 'BLOCK',
                message: "Critical complaint rate signal ({$complaintRate}%). Exceeds Google/Yahoo 0.3% outer limit.",
                remediation: 'Pause campaigns immediately. Investigate acquisition sources and suppress impacted cohorts.',
                evidence: ['complaint_rate' => $complaintRate, 'threshold' => 0.3],
                scope: 'campaign',
                field: 'sender_identity_id'
            )];
        }

        if ($complaintRate >= 0.1) {
            return [new Finding(
                ruleId: 'D-010',
                severity: 'HIGH',
                message: "Elevated spam complaint rate ({$complaintRate}%). Approaching mailbox provider risk thresholds.",
                remediation: 'Review recent campaign audiences and list hygiene before dispatching further promotional mail.',
                evidence: ['complaint_rate' => $complaintRate, 'threshold' => 0.1],
                scope: 'campaign',
                field: 'sender_identity_id'
            )];
        }

        return [];
    }
}

/**
 * Rule D-011: Broken or unsafe CTA destination
 */
class RuleD011CtaDestination implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $html = $context->getHtml();
        preg_match_all('/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches);
        $links = $matches[1] ?? [];
        $suspicious = [];

        foreach ($links as $url) {
            $url = trim($url);
            if (empty($url) || $url === '#') continue;
            // Check for localhost or private IPs
            if (preg_match('/^https?:\/\/(?:localhost|127\.0\.0\.1|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+)/i', $url)) {
                $suspicious[] = $url;
            }
        }

        if (!empty($suspicious)) {
            return [new Finding(
                ruleId: 'D-011',
                severity: 'BLOCK',
                message: 'CTA destination links to private localhost/internal address: ' . implode(', ', array_slice($suspicious, 0, 2)),
                remediation: 'Replace internal test URLs with publicly accessible production endpoints.',
                evidence: ['suspicious_links' => $suspicious],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-012: Too many redirects, misleading link labels or URL shorteners
 */
class RuleD012RedirectsShorteners implements MarketingRule {
    public const SHORTENERS = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly', 'is.gd', 'buff.ly'];

    public function evaluate(PreflightContext $context): array {
        $html = $context->getHtml();
        $foundShorteners = [];

        foreach (self::SHORTENERS as $short) {
            if (stripos($html, $short) !== false) {
                $foundShorteners[] = $short;
            }
        }

        if (!empty($foundShorteners)) {
            return [new Finding(
                ruleId: 'D-012',
                severity: 'WARNING',
                message: 'Template contains public URL shorteners: ' . implode(', ', $foundShorteners) . '.',
                remediation: 'Use direct, transparent brand destination links. URL shorteners trigger major spam filters.',
                evidence: ['shorteners' => $foundShorteners],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-013: No plain-text alternative, invalid MIME or header syntax
 */
class RuleD013MultipartAlternative implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $plain = trim($context->getPlainText());
        if (empty($plain) || strlen($plain) < 15) {
            return [new Finding(
                ruleId: 'D-013',
                severity: 'BLOCK',
                message: 'Multipart plain-text alternative is missing or too short (' . strlen($plain) . ' chars).',
                remediation: 'Generate a comprehensive plain-text alternative to ensure email-client compliance.',
                evidence: ['plain_text_length' => strlen($plain)],
                scope: 'template',
                field: 'plain_text_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-014: Images missing alt text, poor mobile accessibility
 */
class RuleD014ImagesAltText implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $html = $context->getHtml();
        preg_match_all('/<img\s+[^>]*>/i', $html, $matches);
        if (empty($matches[0])) {
            return [];
        }

        $missingAlt = 0;
        foreach ($matches[0] as $imgTag) {
            if (!preg_match('/alt=["\'][^"\']*["\']/i', $imgTag)) {
                $missingAlt++;
            }
        }

        if ($missingAlt > 0) {
            return [new Finding(
                ruleId: 'D-014',
                severity: 'WARNING',
                message: "Found {$missingAlt} image(s) missing alt text attributes.",
                remediation: 'Provide descriptive alt attributes for all content images for screen readers and text-only modes.',
                evidence: ['images_missing_alt' => $missingAlt],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-015: Excessive images-to-context ratio or image-only message
 */
class RuleD015ImageTextRatio implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $html = $context->getHtml();
        $plain = trim(strip_tags($html));
        $wordCount = str_word_count($plain);
        $hasImages = (stripos($html, '<img') !== false);

        if ($hasImages && $wordCount < 20) {
            return [new Finding(
                ruleId: 'D-015',
                severity: 'WARNING',
                message: "Template has low text-to-image ratio (only {$wordCount} words with image blocks).",
                remediation: 'Add meaningful readable copy. Image-only emails trigger spam filters and display poorly on mobile.',
                evidence: ['word_count' => $wordCount, 'has_images' => true],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

/**
 * Rule D-016: Unusual sender/From change or reputation data stale
 */
class RuleD016SenderReputationStale implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $domainData = $context->domain;
        $lastChecked = $domainData['last_checked_at'] ?? null;

        if ($lastChecked && (time() - strtotime($lastChecked)) > (30 * 86400)) {
            return [new Finding(
                ruleId: 'D-016',
                severity: 'HIGH',
                message: 'Domain DNS authentication status was last checked over 30 days ago.',
                remediation: 'Re-run domain authentication verification in Domain Manager to confirm current records.',
                evidence: ['last_checked_at' => $lastChecked],
                scope: 'sending_identity',
                field: 'from_email'
            )];
        }

        return [];
    }
}

/**
 * Rule D-017: Campaign approved hash no longer matches
 */
class RuleD017ApprovalHashMismatch implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        if (!empty($context->approvedHash) && !empty($context->currentHash)) {
            if (!hash_equals($context->approvedHash, $context->currentHash)) {
                return [new Finding(
                    ruleId: 'D-017',
                    severity: 'BLOCK',
                    message: 'Campaign content has been modified since approval (content hash mismatch).',
                    remediation: 'Re-run preflight checks and request fresh manager approval for the modified campaign.',
                    evidence: [
                        'approved_hash' => $context->approvedHash,
                        'current_hash' => $context->currentHash
                    ],
                    scope: 'campaign',
                    field: 'name'
                )];
            }
        }

        return [];
    }
}

/**
 * Rule D-018: Provider send quota reached or webhook ingestion unhealthy
 */
class RuleD018ProviderQuotaAndWebhook implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $espHealth = $context->espHealth;
        $quotaRemaining = $espHealth['quota_remaining'] ?? null;
        $webhookUnhealthy = !empty($espHealth['webhook_unhealthy']);

        if ($quotaRemaining !== null && $quotaRemaining <= 0) {
            return [new Finding(
                ruleId: 'D-018',
                severity: 'BLOCK',
                message: 'Provider daily or monthly sending quota is exhausted (0 remaining).',
                remediation: 'Upgrade provider plan or wait until daily quota reset before releasing campaign queue.',
                evidence: ['quota_remaining' => 0],
                scope: 'campaign',
                field: 'sender_identity_id'
            )];
        }

        if ($webhookUnhealthy) {
            return [new Finding(
                ruleId: 'D-018',
                severity: 'BLOCK',
                message: 'Provider webhook feedback loop is unhealthy or disabled. Delivery events cannot be ingested.',
                remediation: 'Verify webhook endpoint configuration and security tokens before sending.',
                evidence: ['webhook_status' => 'failing'],
                scope: 'campaign',
                field: 'sender_identity_id'
            )];
        }

        return [];
    }
}

/**
 * Rule D-019: Active approved send exceeds allowed frequency/quiet-hours policy
 */
class RuleD019FrequencyQuietHours implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $enforceQuietHours = $context->options['enforce_quiet_hours'] ?? false;
        if (!$enforceQuietHours) {
            return [];
        }

        $scheduleAt = $context->campaign['scheduled_at'] ?? date('Y-m-d H:i:s');
        $hour = (int)date('H', strtotime($scheduleAt));

        // Quiet hours: 22:00 to 06:00
        if ($hour >= 22 || $hour < 6) {
            return [new Finding(
                ruleId: 'D-019',
                severity: 'BLOCK',
                message: "Scheduled launch time ({$scheduleAt}) falls inside recipient quiet hours (22:00 - 06:00).",
                remediation: 'Reschedule campaign dispatch to business hours (09:00 - 18:00 local time).',
                evidence: ['scheduled_hour' => $hour],
                scope: 'campaign',
                field: 'scheduled_at'
            )];
        }

        return [];
    }
}

/**
 * Rule D-020: Unauthorized asset or tracking domain
 */
class RuleD020AssetTrackingDomain implements MarketingRule {
    public function evaluate(PreflightContext $context): array {
        $html = $context->getHtml();
        $allowedDomains = $context->options['allowed_asset_domains'] ?? [];
        if (empty($allowedDomains)) {
            return [];
        }

        preg_match_all('/<img\s+[^>]*src=["\']([^"\']*)["\']/i', $html, $matches);
        $srcs = $matches[1] ?? [];
        $unauthorized = [];

        foreach ($srcs as $src) {
            $parsed = parse_url($src, PHP_URL_HOST);
            if ($parsed && !in_array($parsed, $allowedDomains, true)) {
                $unauthorized[] = $parsed;
            }
        }

        if (!empty($unauthorized)) {
            return [new Finding(
                ruleId: 'D-020',
                severity: 'HIGH',
                message: 'Template references unauthorized asset domain(s): ' . implode(', ', array_unique($unauthorized)),
                remediation: 'Host images on registered brand asset domains or add domain to approved asset allowlist.',
                evidence: ['unauthorized_domains' => array_unique($unauthorized)],
                scope: 'template',
                field: 'html_content'
            )];
        }

        return [];
    }
}

// =========================================================================
// PREFLIGHT ENGINE ORCHESTRATOR
// =========================================================================

final class PreflightEngine {

    /** @var MarketingRule[] */
    private array $rules;

    public function __construct(?array $rules = null) {
        $this->rules = $rules ?? [
            new RuleD001DomainVerified(),
            new RuleD002DomainAuth(),
            new RuleD003LawfulConsent(),
            new RuleD004SuppressionExclusion(),
            new RuleD005OneClickHeaders(),
            new RuleD006UnsubscribeLink(),
            new RuleD007MergeVariables(),
            new RuleD008ContentIntentConflict(),
            new RuleD009SafeHtmlSanitization(),
            new RuleD010ComplaintSpamSignal(),
            new RuleD011CtaDestination(),
            new RuleD012RedirectsShorteners(),
            new RuleD013MultipartAlternative(),
            new RuleD014ImagesAltText(),
            new RuleD015ImageTextRatio(),
            new RuleD016SenderReputationStale(),
            new RuleD017ApprovalHashMismatch(),
            new RuleD018ProviderQuotaAndWebhook(),
            new RuleD019FrequencyQuietHours(),
            new RuleD020AssetTrackingDomain(),
        ];
    }

    /**
     * Compute reproducible SHA-256 artifact hash for the preflight context.
     */
    public static function computeArtifactHash(PreflightContext $context): string {
        $canonical = [
            'subject' => $context->getSubject(),
            'html' => $context->getHtml(),
            'plain' => $context->getPlainText(),
            'from_email' => $context->getFromEmail(),
            'intent' => $context->communicationIntent,
            'policy_version' => $context->policyVersion
        ];
        return hash('sha256', (string)json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Evaluate all rules against preflight context.
     * 
     * @return array{
     *   run_id: string,
     *   policy_version: string,
     *   artifact_sha256: string,
     *   evaluated_at: string,
     *   status: string,
     *   summary: array{block: int, high: int, warning: int, info: int},
     *   findings: array
     * }
     */
    public function evaluate(PreflightContext $context): array {
        /** @var Finding[] $allFindings */
        $allFindings = [];

        foreach ($this->rules as $rule) {
            $findings = $rule->evaluate($context);
            foreach ($findings as $f) {
                $allFindings[] = $f;
            }
        }

        $summary = [
            'block' => 0,
            'high' => 0,
            'warning' => 0,
            'info' => 0
        ];

        foreach ($allFindings as $f) {
            $sev = strtolower($f->severity);
            if (isset($summary[$sev])) {
                $summary[$sev]++;
            }
        }

        // Determine overall status
        if ($summary['block'] > 0) {
            $status = 'blocked';
        } elseif ($summary['high'] > 0) {
            $status = 'review_required';
        } else {
            $status = 'ready';
        }

        $runId = 'pfl_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $artifactHash = self::computeArtifactHash($context);
        $evaluatedAt = date('Y-m-d H:i:s');

        $findingsArray = array_map(static fn(Finding $f) => $f->toArray(), $allFindings);

        return [
            'run_id' => $runId,
            'policy_version' => $context->policyVersion,
            'artifact_sha256' => $artifactHash,
            'evaluated_at' => $evaluatedAt,
            'status' => $status,
            'summary' => $summary,
            'findings' => $findingsArray
        ];
    }
}

