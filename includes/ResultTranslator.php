<?php

declare(strict_types=1);

/**
 * Translates recognition text fields between English and Simplified Chinese.
 * Does not invent new product facts — only language conversion.
 */
final class ResultTranslator
{
    public function __construct(private AppConfig $config)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok:true,data:array<string,mixed>}|array{ok:false,error:string}
     */
    public function translate(array $data, string $lang): array
    {
        $lang = strtolower($lang) === 'zh' ? 'zh' : 'en';

        $payload = [
            'objectLabel' => (string) ($data['objectLabel'] ?? ''),
            'productName' => (string) ($data['productName'] ?? ''),
            'manufacturer' => (string) ($data['manufacturer'] ?? ''),
            'specification' => (string) ($data['specification'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
        ];

        if (trim(implode('', $payload)) === '') {
            return ['ok' => false, 'error' => 'Nothing to translate.'];
        }

        $prompt = $this->buildPrompt($payload, $lang);

        if ($this->config->getString('gemini_api_key') !== '') {
            $text = $this->askGemini($prompt);
            if ($text !== null) {
                $json = $this->extract($text);
                if ($json !== null) {
                    return ['ok' => true, 'data' => $this->merge($data, $json)];
                }
            }
        }

        if ($this->config->getString('agnes_api_key') !== ''
            && $this->config->getString('agnes_endpoint') !== '') {
            $text = $this->askAgnes($prompt);
            if ($text !== null) {
                $json = $this->extract($text);
                if ($json !== null) {
                    return ['ok' => true, 'data' => $this->merge($data, $json)];
                }
            }
        }

        return [
            'ok' => false,
            'error' => $lang === 'zh' ? '无法翻译识别结果。' : 'Unable to translate recognition result.',
        ];
    }

    /**
     * @param array<string, string> $payload
     */
    private function buildPrompt(array $payload, string $lang): string
    {
        $target = $lang === 'zh' ? 'Simplified Chinese (简体中文)' : 'English';
        $jsonIn = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Translate the following product recognition JSON into {$target}.

Rules:
1. Translate ALL fields completely: objectLabel, productName, manufacturer, specification, description.
2. Every human-readable value MUST be fully in the target language.
3. Do NOT invent new facts, models, or brands.
4. Brand names may keep their original Latin spelling (e.g. Huawei, Razer), but all other words must be translated.
5. If a value is Unknown / N/A, translate it to 未知 / Unknown as appropriate.
6. Return ONLY valid JSON with the same keys. No markdown.

Input JSON:
{$jsonIn}
PROMPT;
    }

    private function askGemini(string $prompt): ?string
    {
        $models = [];
        $primary = trim($this->config->getString('gemini_model', 'gemini-flash-latest'));
        if ($primary !== '') {
            $models[] = $primary;
        }
        foreach (preg_split('/[\r\n,]+/', $this->config->getString('gemini_fallback_models')) ?: [] as $fb) {
            $fb = trim($fb);
            if ($fb !== '' && !in_array($fb, $models, true)) {
                $models[] = $fb;
            }
        }

        $base = rtrim($this->config->getString(
            'gemini_endpoint',
            'https://generativelanguage.googleapis.com/v1beta/models'
        ), '/');
        $apiKey = $this->config->getString('gemini_api_key');

        $body = [
            'contents' => [[
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        foreach ($models as $model) {
            $url = $base . '/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
            $raw = $this->postJson($url, $body);
            if ($raw === null) {
                continue;
            }
            $decoded = json_decode($raw, true);
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }

    private function askAgnes(string $prompt): ?string
    {
        $endpoint = rtrim($this->config->getString('agnes_endpoint'), '/');
        if (str_ends_with($endpoint, '/chat/completions')) {
            // ok
        } elseif (str_ends_with($endpoint, '/v1')) {
            $endpoint .= '/chat/completions';
        } else {
            $endpoint .= '/chat/completions';
        }

        $models = [];
        $primary = trim($this->config->getString('agnes_model', 'agnes-3.0-flash'));
        if ($primary !== '') {
            $models[] = $primary;
        }
        foreach (preg_split('/[\r\n,]+/', $this->config->getString('agnes_fallback_models')) ?: [] as $fb) {
            $fb = trim($fb);
            if ($fb !== '' && !in_array($fb, $models, true)) {
                $models[] = $fb;
            }
        }

        foreach ($models as $model) {
            $body = [
                'model' => $model,
                'temperature' => 0.1,
                'max_tokens' => 800,
                'messages' => [[
                    'role' => 'user',
                    'content' => $prompt,
                ]],
            ];

            $ch = curl_init($endpoint);
            if ($ch === false) {
                continue;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->config->getString('agnes_api_key'),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => max(10, min(120, $this->config->getInt('request_timeout', 45))),
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw === false || $status >= 400) {
                continue;
            }
            $decoded = json_decode((string) $raw, true);
            $text = $decoded['choices'][0]['message']['content'] ?? '';
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $url, array $payload): ?string
    {
        $timeout = max(10, min(120, $this->config->getInt('request_timeout', 45)));
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $status >= 400) {
            return null;
        }
        return (string) $raw;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extract(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $translated
     * @return array<string, mixed>
     */
    private function merge(array $original, array $translated): array
    {
        foreach (['objectLabel', 'productName', 'manufacturer', 'specification', 'description'] as $key) {
            if (isset($translated[$key]) && is_string($translated[$key]) && trim($translated[$key]) !== '') {
                $original[$key] = trim($translated[$key]);
            }
        }
        return $original;
    }
}
