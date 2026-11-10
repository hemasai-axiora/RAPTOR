<?php
/**
 * Raptor CRM — Universal AI & LLM Service
 * -------------------------------------------------------------------------
 * Multi-provider abstraction supporting Google Gemini, OpenAI GPT, and
 * Anthropic Claude. Handles key management, live connection testing,
 * prompt dispatches, and error handling.
 */

declare(strict_types=1);

class AiService {
    public const PROVIDER_GEMINI    = 'gemini';
    public const PROVIDER_OPENAI    = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';

    public const DEFAULT_GEMINI_MODEL    = 'gemini-2.5-flash';
    public const DEFAULT_OPENAI_MODEL    = 'gpt-4o-mini';
    public const DEFAULT_ANTHROPIC_MODEL = 'claude-3-5-haiku-20241022';

    /**
     * Get a setting with fallback to environment variables.
     */
    public static function getSetting(string $key, ?string $default = null): ?string {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1');
            $stmt->execute([':k' => $key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null && trim((string)$val) !== '') {
                return trim((string)$val);
            }
        } catch (\Throwable $e) {
            // DB fallback
        }

        // Env fallback
        $envMap = [
            'gemini_api_key'      => 'GEMINI_API_KEY',
            'openai_api_key'      => 'OPENAI_API_KEY',
            'anthropic_api_key'   => 'ANTHROPIC_API_KEY',
            'ai_default_provider' => 'AI_DEFAULT_PROVIDER',
        ];

        $envKey = $envMap[$key] ?? strtoupper(str_replace('.', '_', $key));
        $envVal = getenv($envKey);
        if ($envVal !== false && $envVal !== '') {
            return $envVal;
        }

        return $default;
    }

    /**
     * Test connection to a specific AI provider.
     *
     * @param string $provider 'gemini', 'openai', or 'anthropic'
     * @param string|null $apiKey If null, loaded from settings
     * @param string|null $model If null, default for provider is used
     * @return array{success: bool, provider: string, model: string, latency_ms: int, message: string}
     */
    public static function testConnection(string $provider, ?string $apiKey = null, ?string $model = null): array {
        $startTime = microtime(true);

        switch (strtolower($provider)) {
            case self::PROVIDER_GEMINI:
                $key = $apiKey ?: self::getSetting('gemini_api_key');
                $mod = $model ?: self::getSetting('gemini_model', self::DEFAULT_GEMINI_MODEL);
                return self::testGemini($key, $mod, $startTime);

            case self::PROVIDER_OPENAI:
                $key = $apiKey ?: self::getSetting('openai_api_key');
                $mod = $model ?: self::getSetting('openai_model', self::DEFAULT_OPENAI_MODEL);
                return self::testOpenAi($key, $mod, $startTime);

            case self::PROVIDER_ANTHROPIC:
                $key = $apiKey ?: self::getSetting('anthropic_api_key');
                $mod = $model ?: self::getSetting('anthropic_model', self::DEFAULT_ANTHROPIC_MODEL);
                return self::testAnthropic($key, $mod, $startTime);

            default:
                return [
                    'success'    => false,
                    'provider'   => $provider,
                    'model'      => $model ?? 'unknown',
                    'latency_ms' => 0,
                    'message'    => "Unsupported AI provider: {$provider}",
                ];
        }
    }

    /**
     * Test Google Gemini API
     */
    private static function testGemini(?string $apiKey, string $model, float $startTime): array {
        if (empty($apiKey)) {
            return [
                'success'    => false,
                'provider'   => self::PROVIDER_GEMINI,
                'model'      => $model,
                'latency_ms' => 0,
                'message'    => 'Gemini API key is missing or empty.',
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => 'Respond with the single word: OK']]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 5,
                'temperature'     => 0.0,
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => defined('SENTRY_SSL_VERIFY') ? SENTRY_SSL_VERIFY : true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($curlErr) {
            return [
                'success'    => false,
                'provider'   => self::PROVIDER_GEMINI,
                'model'      => $model,
                'latency_ms' => $latency,
                'message'    => "cURL network error: {$curlErr}",
            ];
        }

        $json = json_decode((string)$response, true);

        if ($httpCode === 200 && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'success'    => true,
                'provider'   => self::PROVIDER_GEMINI,
                'model'      => $model,
                'latency_ms' => $latency,
                'message'    => "Connected successfully! Response: " . trim($json['candidates'][0]['content']['parts'][0]['text']),
            ];
        }

        $errMsg = $json['error']['message'] ?? "HTTP error {$httpCode}";
        return [
            'success'    => false,
            'provider'   => self::PROVIDER_GEMINI,
            'model'      => $model,
            'latency_ms' => $latency,
            'message'    => "Gemini API rejected request: {$errMsg}",
        ];
    }

    /**
     * Test OpenAI API
     */
    private static function testOpenAi(?string $apiKey, string $model, float $startTime): array {
        if (empty($apiKey)) {
            return [
                'success'    => false,
                'provider'   => self::PROVIDER_OPENAI,
                'model'      => $model,
                'latency_ms' => 0,
                'message'    => 'OpenAI API key is missing or empty.',
            ];
        }

        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = json_encode([
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => 'Respond with the word OK']],
            'max_tokens'  => 5,
            'temperature' => 0.0,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => defined('SENTRY_SSL_VERIFY') ? SENTRY_SSL_VERIFY : true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($curlErr) {
            return [
                'success'    => false,
                'provider'   => self::PROVIDER_OPENAI,
                'model'      => $model,
                'latency_ms' => $latency,
                'message'    => "cURL network error: {$curlErr}",
            ];
        }

        $json = json_decode((string)$response, true);

        if ($httpCode === 200 && isset($json['choices'][0]['message']['content'])) {
            return [
                'success'    => true,
                'provider'   => self::PROVIDER_OPENAI,
                'model'      => $model,
                'latency_ms' => $latency,
                'message'    => "Connected successfully! Response: " . trim($json['choices'][0]['message']['content']),
            ];
        }

        $errMsg = $json['error']['message'] ?? "HTTP error {$httpCode}";
        return [
            'success'    => false,
            'provider'   => self::PROVIDER_OPENAI,
            'model'      => $model,
            'latency_ms' => $latency,
            'message'    => "OpenAI rejected request: {$errMsg}",
        ];
    }

    /**
     * Test Anthropic Claude API
     */
    private static function testAnthropic(?string $apiKey, string $model, float $startTime): array {
        if (empty($apiKey)) {
            return [
                'success'    => false,
                'provider'   => self::PROVIDER_ANTHROPIC,
                'model'      => $model,
                'latency_ms' => 0,
                'message'    => 'Anthropic API key is missing or empty.',
            ];
        }

        $url = 'https://api.anthropic.com/v1/messages';
        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => 5,
            'messages'   => [['role' => 'user', 'content' => 'Respond with the word OK']],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => defined('SENTRY_SSL_VERIFY') ? SENTRY_SSL_VERIFY : true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $latency = (int) round((microtime(true) - $startTime) * 1000);

        if ($curlErr) {
            return [
                'success'    => false,
                'provider'   => self::PROVIDER_ANTHROPIC,
                'model'      => $model,
                'latency_ms' => $latency,
                'message'    => "cURL network error: {$curlErr}",
            ];
        }

        $json = json_decode((string)$response, true);

        if ($httpCode === 200 && isset($json['content'][0]['text'])) {
            return [
                'success'    => true,
                'provider'   => self::PROVIDER_ANTHROPIC,
                'model'      => $model,
                'latency_ms' => $latency,
                'message'    => "Connected successfully! Response: " . trim($json['content'][0]['text']),
            ];
        }

        $errMsg = $json['error']['message'] ?? "HTTP error {$httpCode}";
        return [
            'success'    => false,
            'provider'   => self::PROVIDER_ANTHROPIC,
            'model'      => $model,
            'latency_ms' => $latency,
            'message'    => "Anthropic rejected request: {$errMsg}",
        ];
    }

    /**
     * Generate text using the configured default provider (or explicit override).
     */
    public static function generate(string $prompt, array $options = []): array {
        $provider = $options['provider'] ?? self::getSetting('ai_default_provider', self::PROVIDER_GEMINI);
        $temperature = (float) ($options['temperature'] ?? self::getSetting('ai_temperature', '0.7'));
        $maxTokens = (int) ($options['max_tokens'] ?? self::getSetting('ai_max_tokens', '2048'));

        switch (strtolower($provider)) {
            case self::PROVIDER_OPENAI:
                $key = self::getSetting('openai_api_key');
                $model = $options['model'] ?? self::getSetting('openai_model', self::DEFAULT_OPENAI_MODEL);
                return self::callOpenAi($prompt, $key, $model, $temperature, $maxTokens);

            case self::PROVIDER_ANTHROPIC:
                $key = self::getSetting('anthropic_api_key');
                $model = $options['model'] ?? self::getSetting('anthropic_model', self::DEFAULT_ANTHROPIC_MODEL);
                return self::callAnthropic($prompt, $key, $model, $temperature, $maxTokens);

            case self::PROVIDER_GEMINI:
            default:
                $key = self::getSetting('gemini_api_key');
                $model = $options['model'] ?? self::getSetting('gemini_model', self::DEFAULT_GEMINI_MODEL);
                return self::callGemini($prompt, $key, $model, $temperature, $maxTokens);
        }
    }

    private static function callGemini(string $prompt, ?string $key, string $model, float $temp, int $maxTokens): array {
        if (empty($key)) {
            return ['success' => false, 'error' => 'Gemini API key is not configured.'];
        }
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($key);
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'     => $temp,
                'maxOutputTokens' => $maxTokens,
            ]
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => defined('SENTRY_SSL_VERIFY') ? SENTRY_SSL_VERIFY : true,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$res, true);
        if ($code === 200 && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'success'  => true,
                'provider' => self::PROVIDER_GEMINI,
                'model'    => $model,
                'text'     => trim($json['candidates'][0]['content']['parts'][0]['text']),
            ];
        }
        return ['success' => false, 'error' => $json['error']['message'] ?? "Gemini API error {$code}"];
    }

    private static function callOpenAi(string $prompt, ?string $key, string $model, float $temp, int $maxTokens): array {
        if (empty($key)) {
            return ['success' => false, 'error' => 'OpenAI API key is not configured.'];
        }
        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = json_encode([
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $temp,
            'max_tokens'  => $maxTokens,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => defined('SENTRY_SSL_VERIFY') ? SENTRY_SSL_VERIFY : true,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$res, true);
        if ($code === 200 && isset($json['choices'][0]['message']['content'])) {
            return [
                'success'  => true,
                'provider' => self::PROVIDER_OPENAI,
                'model'    => $model,
                'text'     => trim($json['choices'][0]['message']['content']),
            ];
        }
        return ['success' => false, 'error' => $json['error']['message'] ?? "OpenAI API error {$code}"];
    }

    private static function callAnthropic(string $prompt, ?string $key, string $model, float $temp, int $maxTokens): array {
        if (empty($key)) {
            return ['success' => false, 'error' => 'Anthropic API key is not configured.'];
        }
        $url = 'https://api.anthropic.com/v1/messages';
        $payload = json_encode([
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'temperature' => $temp,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => defined('SENTRY_SSL_VERIFY') ? SENTRY_SSL_VERIFY : true,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$res, true);
        if ($code === 200 && isset($json['content'][0]['text'])) {
            return [
                'success'  => true,
                'provider' => self::PROVIDER_ANTHROPIC,
                'model'    => $model,
                'text'     => trim($json['content'][0]['text']),
            ];
        }
        return ['success' => false, 'error' => $json['error']['message'] ?? "Anthropic API error {$code}"];
    }
}
