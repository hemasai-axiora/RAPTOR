<?php
/**
 * Raptor CRM — Multi-Provider AI Recruitment Copilot (Sprint 9)
 * -------------------------------------------------------------------------
 * Provides Human-in-the-Loop AI intelligence for candidate resume parsing,
 * candidate-job evidence matching, and interview question suggestions.
 * Strictly adheres to ADR-0007: Human Oversight, Mandatory PII Redaction,
 * and 100% resilient offline fallback when AI keys are unavailable.
 */

declare(strict_types=1);

require_once __DIR__ . '/../AiService.php';

class RecruitmentAiService {

    public const SKILL_LEXICON = [
        'php', 'mysql', 'postgresql', 'sqlite', 'docker', 'kubernetes', 'aws', 'gcp', 'azure',
        'linux', 'python', 'javascript', 'typescript', 'vue', 'react', 'angular', 'node.js',
        'redis', 'elasticsearch', 'rest api', 'graphql', 'ci/cd', 'git', 'microservices',
        'system design', 'distributed systems', 'devops', 'terraform', 'security', 'oauth2',
        'phpunit', 'agile', 'scrum', 'data modeling', 'architecture'
    ];

    /**
     * Redact Personally Identifiable Information (PII) before LLM prompt transmission.
     * Complies with DPDP (Digital Personal Data Protection) & GDPR safeguards.
     */
    public function redactPii(string $text): string {
        // 1. Redact Emails
        $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', '[REDACTED_EMAIL]', $text);

        // 2. Redact Indian & International Phone Numbers
        $text = preg_replace('/(?:\+?91[\s.-]?)?(?:[6-9]\d{4}[\s.-]?\d{5}|[6-9]\d{9})\b/', '[REDACTED_PHONE]', $text);
        $text = preg_replace('/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/', '[REDACTED_PHONE]', $text);

        // 3. Redact Indian Aadhaar Numbers (12 digits, optional spacing)
        $text = preg_replace('/\b\d{4}\s?\d{4}\s?\d{4}\b/', '[REDACTED_AADHAAR]', $text);

        // 4. Redact Indian PAN Card Numbers (5 letters, 4 digits, 1 letter)
        $text = preg_replace('/\b[A-Z]{5}[0-9]{4}[A-Z]\b/', '[REDACTED_PAN]', $text);

        // 5. Redact Specific Postal Addresses patterns (Street/Pin Code/Postal Code)
        $text = preg_replace('/(?:Pincode|Pin Code|Zip|Postal Code)[\s:]+\d{6}/i', '[REDACTED_POSTAL_CODE]', $text);

        return $text;
    }

    /**
     * Parse raw resume text into structured candidate profile data.
     * Uses configured AI provider if available; otherwise falls back to robust offline heuristic engine.
     */
    public function parseResume(string $rawResumeText, ?PDO $db = null): array {
        $redactedText = $this->redactPii($rawResumeText);

        $activeProvider = $this->detectActiveAiProvider();

        if ($activeProvider !== null) {
            try {
                $parsed = $this->parseResumeViaAi($redactedText, $activeProvider);
                if (!empty($parsed['skills'])) {
                    $parsed['provider_used'] = $activeProvider;
                    $parsed['is_ai_generated'] = true;
                    return $parsed;
                }
            } catch (Throwable $e) {
                // Graceful fallback on LLM failure or timeout
                error_log("RecruitmentAiService::parseResume LLM Exception: " . $e->getMessage());
            }
        }

        // Offline Heuristic Engine Fallback
        return $this->parseResumeOffline($redactedText);
    }

    /**
     * Generate an advisory candidate-job match score and evidence breakdown.
     * Mandatory Guard: Result is strictly marked with human_action_required = true (ADR-0007).
     */
    public function scoreCandidateJobMatch(string $resumeText, array $jobRequirements, ?PDO $db = null): array {
        $redactedText = $this->redactPii($resumeText);
        $reqSkills = $jobRequirements['required_skills'] ?? ($jobRequirements['skills'] ?? []);
        if (is_string($reqSkills)) {
            $reqSkills = array_map('trim', explode(',', strtolower($reqSkills)));
        } else {
            $reqSkills = array_map(fn($s) => strtolower(trim((string)$s)), (array)$reqSkills);
        }

        $minExpYears = (float)($jobRequirements['min_experience_years'] ?? 0.0);
        $jobTitle = (string)($jobRequirements['job_title'] ?? 'Role');

        // Extract candidate profile from resume
        $candidateData = $this->parseResume($redactedText, $db);
        $candSkills = array_map('strtolower', $candidateData['skills'] ?? []);
        $candExp = (float)($candidateData['experience_years'] ?? 0.0);

        // Calculate skill overlap & evidence
        $matchedSkills = [];
        $missingSkills = [];

        foreach ($reqSkills as $skill) {
            $skill = trim($skill);
            if (empty($skill)) continue;

            $matched = false;
            foreach ($candSkills as $cSkill) {
                if ($skill === $cSkill || str_contains($cSkill, $skill) || str_contains($skill, $cSkill)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched && str_contains(strtolower($redactedText), $skill)) {
                $matched = true;
            }

            if ($matched) {
                $matchedSkills[] = ucfirst($skill);
            } else {
                $missingSkills[] = ucfirst($skill);
            }
        }

        // Scoring algorithm:
        // Skills match: 70% weight, Experience match: 30% weight
        $skillScore = !empty($reqSkills) ? (count($matchedSkills) / count($reqSkills)) * 70.0 : 50.0;
        
        $expScore = 30.0;
        if ($minExpYears > 0) {
            $expRatio = min(1.0, $candExp / $minExpYears);
            $expScore = $expRatio * 30.0;
        }

        $totalScore = (int)round($skillScore + $expScore);
        $totalScore = max(10, min(98, $totalScore)); // Boundary limits for advisory score

        // Recommendation level
        $recommendation = 'Neutral Consideration';
        if ($totalScore >= 80) {
            $recommendation = 'Strong Match - Recommended for Screening';
        } elseif ($totalScore >= 65) {
            $recommendation = 'Potential Match - Minor Skill Gaps to Verify';
        } else {
            $recommendation = 'Low Match - Substantial Skill/Experience Variance';
        }

        return [
            'match_score'           => $totalScore,
            'recommendation'        => $recommendation,
            'matched_skills'        => $matchedSkills,
            'missing_skills'        => $missingSkills,
            'candidate_experience'  => $candExp,
            'required_experience'   => $minExpYears,
            'evidence_summary'      => "Candidate demonstrates " . count($matchedSkills) . " of " . count($reqSkills) . " required core competencies with {$candExp} years of relevant experience.",
            'human_action_required' => true,
            'advisory_only'         => true,
            'governance_notice'     => 'ADR-0007 Mandatory Policy: This match score is an advisory guideline only. Human recruiter discretion is required for all candidate screening and progression decisions.'
        ];
    }

    /**
     * Suggest 5 structured interview questions tailored to a job title, round, and candidate skills.
     */
    public function generateInterviewQuestions(
        string $jobTitle,
        array $skills = [],
        string $roundType = 'technical',
        ?PDO $db = null
    ): array {
        $activeProvider = $this->detectActiveAiProvider();

        if ($activeProvider !== null) {
            try {
                $questions = $this->generateQuestionsViaAi($jobTitle, $skills, $roundType, $activeProvider);
                if (count($questions) >= 3) {
                    return [
                        'success'         => true,
                        'provider_used'   => $activeProvider,
                        'is_ai_generated' => true,
                        'round_type'      => $roundType,
                        'questions'       => $questions
                    ];
                }
            } catch (Throwable $e) {
                error_log("RecruitmentAiService::generateInterviewQuestions Exception: " . $e->getMessage());
            }
        }

        // Return curated domain fallback question bank
        return [
            'success'         => true,
            'provider_used'   => 'curated_bank_offline',
            'is_ai_generated' => false,
            'round_type'      => $roundType,
            'questions'       => $this->getCuratedQuestionBank($jobTitle, $skills, $roundType)
        ];
    }

    // =========================================================================
    // PRIVATE HELPER IMPLEMENTATIONS
    // =========================================================================

    private function detectActiveAiProvider(): ?string {
        if (!empty(AiService::getSetting('gemini_api_key'))) {
            return AiService::PROVIDER_GEMINI;
        }
        if (!empty(AiService::getSetting('openai_api_key'))) {
            return AiService::PROVIDER_OPENAI;
        }
        if (!empty(AiService::getSetting('anthropic_api_key'))) {
            return AiService::PROVIDER_ANTHROPIC;
        }
        return null;
    }

    private function parseResumeOffline(string $text): array {
        $lowerText = strtolower($text);

        // 1. Skill Extraction
        $foundSkills = [];
        foreach (self::SKILL_LEXICON as $skill) {
            $pattern = '/\b' . preg_quote($skill, '/') . '\b/i';
            if (preg_match($pattern, $lowerText)) {
                $foundSkills[] = ucfirst($skill);
            }
        }

        // 2. Experience Years Extraction
        $experienceYears = 0.0;
        if (preg_match('/(\d+(?:\.\d+)?)\+?\s*(?:years?|yrs?)(?:\s+(?:of\s+)?experience)?/i', $text, $m)) {
            $experienceYears = (float)$m[1];
        }

        // 3. Education Extraction
        $education = [];
        $eduPatterns = [
            'B.Tech' => '/\b(?:B\.?Tech|Bachelor of Technology)\b/i',
            'B.E.'   => '/\b(?:B\.?E\.?|Bachelor of Engineering)\b/i',
            'M.Tech' => '/\b(?:M\.?Tech|Master of Technology)\b/i',
            'MCA'    => '/\b(?:MCA|Master of Computer Applications)\b/i',
            'B.Sc'   => '/\b(?:B\.?Sc|Bachelor of Science)\b/i',
            'MBA'    => '/\b(?:MBA|Master of Business Administration)\b/i'
        ];
        foreach ($eduPatterns as $degree => $pattern) {
            if (preg_match($pattern, $text)) {
                $education[] = $degree;
            }
        }

        return [
            'skills'           => array_values(array_unique($foundSkills)),
            'experience_years' => $experienceYears,
            'education'        => $education,
            'provider_used'    => 'heuristic_offline',
            'is_ai_generated'  => false
        ];
    }

    private function parseResumeViaAi(string $redactedText, string $provider): array {
        $prompt = "You are an enterprise HR parsing assistant. Analyze this resume text and extract candidate profile attributes into valid JSON with these exact keys: 'skills' (array of strings), 'experience_years' (float number), 'education' (array of strings), 'summary' (brief string).\n\nResume Text:\n" . substr($redactedText, 0, 4000);

        $response = AiService::dispatchPrompt($provider, $prompt, ['temperature' => 0.1]);
        $content = trim($response['text'] ?? '');

        // Extract JSON block
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (is_array($json) && isset($json['skills'])) {
                return $json;
            }
        }

        return [];
    }

    private function generateQuestionsViaAi(string $jobTitle, array $skills, string $roundType, string $provider): array {
        $skillStr = implode(', ', $skills);
        $prompt = "Generate exactly 5 insightful interview questions for a '{$jobTitle}' candidate during a '{$roundType}' round. Relevant skills: {$skillStr}. Return as JSON array of objects with keys: 'question', 'category', 'evaluation_criteria'.";

        $response = AiService::dispatchPrompt($provider, $prompt, ['temperature' => 0.4]);
        $content = trim($response['text'] ?? '');

        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (is_array($json) && count($json) >= 3) {
                return $json;
            }
        }

        return [];
    }

    private function getCuratedQuestionBank(string $jobTitle, array $skills, string $roundType): array {
        if ($roundType === 'technical') {
            return [
                [
                    'question'            => "How would you design a high-throughput, fault-tolerant background queue processing system for {$jobTitle} workloads?",
                    'category'            => 'Architecture & Scalability',
                    'evaluation_criteria' => 'Demonstrates knowledge of concurrency control, idempotency, retry backoffs, and dead-letter queues.'
                ],
                [
                    'question'            => "Explain how you handle database transaction isolation, row locking (e.g. SKIP LOCKED), and connection pooling under heavy write load.",
                    'category'            => 'Database Engineering',
                    'evaluation_criteria' => 'Distinguishes between pessimistic and optimistic locking; explains deadlock prevention.'
                ],
                [
                    'question'            => "Walk us through an incident where a critical production bug occurred. How did you diagnose, resolve, and prevent regressions?",
                    'category'            => 'Debugging & Incident Management',
                    'evaluation_criteria' => 'Structured root-cause analysis, telemetry usage (logs/traces), automated regression test authoring.'
                ],
                [
                    'question'            => "What strategies do you employ to enforce strict data privacy (PII masking, role-based access control, tenant isolation) in modern enterprise web applications?",
                    'category'            => 'Security & Compliance',
                    'evaluation_criteria' => 'Awareness of OWASP Top 10, sanitization, DPDP/GDPR principles, and defense-in-depth authorization.'
                ],
                [
                    'question'            => "How do you evaluate when to adopt an asynchronous event-driven pattern versus synchronous REST API calls?",
                    'category'            => 'API & Integration Patterns',
                    'evaluation_criteria' => 'Understands latency trade-offs, coupling, eventual consistency, and operational observability.'
                ]
            ];
        }

        if ($roundType === 'hr' || $roundType === 'culture') {
            return [
                [
                    'question'            => "Describe a situation where you had a strong technical disagreement with a team member. How did you navigate the conversation to an agreed outcome?",
                    'category'            => 'Collaboration & Conflict Resolution',
                    'evaluation_criteria' => 'Empathy, objective data-driven argumentation, and commitment to team alignment.'
                ],
                [
                    'question'            => "What motivated you to apply for the {$jobTitle} vacancy at Raptor, and how does this role align with your 3-year professional trajectory?",
                    'category'            => 'Motivation & Cultural Fit',
                    'evaluation_criteria' => 'Understanding of platform domain, personal career vision, and passion for enterprise software.'
                ],
                [
                    'question'            => "Tell us about a time when project scope or business priorities shifted abruptly. How did you adapt your workflow and deliverables?",
                    'category'            => 'Adaptability & Resilience',
                    'evaluation_criteria' => 'Flexibility, transparent stakeholder communication, and practical prioritization.'
                ],
                [
                    'question'            => "How do you maintain high engineering quality while balancing urgent commercial release deadlines?",
                    'category'            => 'Execution & Pragmatism',
                    'evaluation_criteria' => 'Technical debt management, incremental delivery, test automation, and risk mitigation.'
                ],
                [
                    'question'            => "What kind of team environment and leadership style enables you to perform at your absolute best?",
                    'category'            => 'Workplace Environment',
                    'evaluation_criteria' => 'Self-awareness, autonomy requirements, and constructive collaboration preferences.'
                ]
            ];
        }

        // Default General Questions
        return [
            [
                'question'            => "Can you provide a high-level overview of the most challenging project you delivered in your recent role as {$jobTitle}?",
                'category'            => 'Project Experience',
                'evaluation_criteria' => 'Clarity of technical thought, ownership, and measurable impact on business goals.'
            ],
            [
                'question'            => "How do you approach learning new technologies and architectural patterns when business requirements demand it?",
                'category'            => 'Continuous Learning',
                'evaluation_criteria' => 'Curiosity, structured learning methodology, and rapid prototyping skills.'
            ],
            [
                'question'            => "What role does automated testing play in your day-to-day software development lifecycle?",
                'category'            => 'Quality Assurance',
                'evaluation_criteria' => 'TDD/BDD awareness, zero-regression commitment, and unit/integration test coverage.'
            ]
        ];
    }
}

