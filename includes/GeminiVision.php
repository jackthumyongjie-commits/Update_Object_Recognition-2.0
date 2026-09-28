<?php

declare(strict_types=1);

/**
 * Google Gemini Vision provider.
 */
final class GeminiVision extends AIProvider
{
    public function getName(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return $this->config->getString('gemini_api_key') !== '';
    }

    public function recognize(string $imageBinary, string $mime, string $lang = 'en'): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Gemini API key is not configured.'];
        }

        $models = $this->parseModelList(
            $this->config->getString('gemini_model', 'gemini-flash-latest'),
            $this->config->getString('gemini_fallback_models')
        );

        if ($models === []) {
            return ['ok' => false, 'error' => 'No Gemini model configured.'];
        }

        $base = rtrim($this->config->getString(
            'gemini_endpoint',
            'https://generativelanguage.googleapis.com/v1beta/models'
        ), '/');

        $apiKey = $this->config->getString('gemini_api_key');
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $this->buildVisionPrompt($lang)],
                    [
                        'inlineData' => [
                            'mimeType' => $mime,
                            'data' => base64_encode($imageBinary),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        $lastError = 'Gemini recognition failed.';

        foreach ($models as $model) {
            $url = $base . '/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
            $response = $this->httpPostJson($url, $payload);

            if (!$response['ok']) {
                $lastError = $response['error'];
                continue;
            }

            if ($response['status'] >= 400) {
                AppConfig::log('Gemini HTTP ' . $response['status'] . ' for model ' . $model);
                $lastError = $this->friendlyHttpError($response['status'], $response['body'], $model);
                continue;
            }

            $decoded = json_decode($response['body'], true);
            if (!is_array($decoded)) {
                $lastError = 'Invalid response from Gemini.';
                continue;
            }

            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!is_string($text) || trim($text) === '') {
                // Some responses place JSON elsewhere
                $text = json_encode($decoded);
            }

            $json = $this->extractJsonObject((string) $text);
            if ($json === null) {
                $lastError = 'Gemini returned incomplete JSON.';
                continue;
            }

            $result = ProductResult::fromArray($json, 'gemini');
            if (!$result->isComplete()) {
                $lastError = 'Gemini returned an incomplete result.';
                continue;
            }

            return ['ok' => true, 'result' => $result];
        }

        return ['ok' => false, 'error' => $lastError];
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'API key not configured'];
        }

        $models = $this->parseModelList(
            $this->config->getString('gemini_model', 'gemini-flash-latest'),
            $this->config->getString('gemini_fallback_models')
        );

        if ($models === []) {
            return ['ok' => false, 'message' => 'No Gemini model configured'];
        }

        $base = rtrim($this->config->getString(
            'gemini_endpoint',
            'https://generativelanguage.googleapis.com/v1beta/models'
        ), '/');
        $apiKey = $this->config->getString('gemini_api_key');

        $payload = [
            'contents' => [[
                'parts' => [['text' => 'Reply with JSON only: {"ok":true}']],
            ]],
            'generationConfig' => [
                'temperature' => 0,
                'maxOutputTokens' => 32,
                'responseMimeType' => 'application/json',
            ],
        ];

        $lastMessage = 'Connection failed';

        foreach ($models as $model) {
            $url = $base . '/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
            $response = $this->httpPostJson($url, $payload);

            if (!$response['ok']) {
                $lastMessage = $response['error'];
                continue;
            }

            if ($response['status'] >= 400) {
                $lastMessage = $this->friendlyHttpError($response['status'], $response['body'], $model);
                continue;
            }

            return ['ok' => true, 'message' => 'Connected (' . $model . ')'];
        }

        return ['ok' => false, 'message' => $lastMessage];
    }

    private function friendlyHttpError(int $status, string $body, string $model): string
    {
        $decoded = json_decode($body, true);
        $apiMessage = '';
        if (is_array($decoded) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            $apiMessage = trim($decoded['error']['message']);
            // Never echo secrets if Google somehow echoed the key
            $apiMessage = preg_replace('/key=[^&\s"]+/i', 'key=***', $apiMessage) ?? $apiMessage;
        }

        if ($status === 404) {
            return 'Model not available: ' . $model
                . '. Try gemini-flash-latest or gemini-3.6-flash.'
                . ($apiMessage !== '' ? ' (' . $this->truncate($apiMessage, 160) . ')' : '');
        }

        if ($status === 401 || $status === 403) {
            return 'Invalid or unauthorized Gemini API key.'
                . ($apiMessage !== '' ? ' (' . $this->truncate($apiMessage, 120) . ')' : '');
        }

        if ($status === 429) {
            return 'Gemini quota exceeded. Check billing/plan in Google AI Studio.';
        }

        return 'Connection failed (HTTP ' . $status . ')'
            . ($apiMessage !== '' ? ': ' . $this->truncate($apiMessage, 160) : '');
    }

    private function truncate(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        return rtrim(substr($text, 0, $max - 1)) . '…';
    }
}
