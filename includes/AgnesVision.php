<?php

declare(strict_types=1);

/**
 * Agnes Vision provider — Agnes AI OpenAI-compatible chat/completions API.
 *
 * Official base URL: https://apihub.agnes-ai.com/v1
 * Request URL:       https://apihub.agnes-ai.com/v1/chat/completions
 *
 * Settings may store either the base URL (/v1) or the full chat URL.
 */
final class AgnesVision extends AIProvider
{
    public function getName(): string
    {
        return 'agnes';
    }

    public function isConfigured(): bool
    {
        return $this->config->getString('agnes_api_key') !== ''
            && $this->config->getString('agnes_endpoint') !== '';
    }

    public function recognize(string $imageBinary, string $mime, string $lang = 'en'): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Agnes API is not configured.'];
        }

        $models = $this->parseModelList(
            $this->config->getString('agnes_model', 'agnes-3.0-flash'),
            $this->config->getString('agnes_fallback_models')
        );

        if ($models === []) {
            return ['ok' => false, 'error' => 'No Agnes model configured.'];
        }

        $endpoint = $this->normalizeChatCompletionsUrl($this->config->getString('agnes_endpoint'));
        if ($endpoint === '' || $this->isPlaceholderEndpoint($endpoint)) {
            return [
                'ok' => false,
                'error' => 'Agnes endpoint is invalid. Use https://apihub.agnes-ai.com/v1',
            ];
        }

        $apiKey = $this->config->getString('agnes_api_key');
        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($imageBinary);

        $lastError = 'Agnes recognition failed.';

        foreach ($models as $model) {
            $payload = [
                'model' => $model,
                'temperature' => 0.2,
                'max_tokens' => 1024,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->buildVisionPrompt($lang)],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ]],
            ];

            $response = $this->httpPostJson($endpoint, $payload, [
                'headers' => ['Authorization: Bearer ' . $apiKey],
            ]);

            if (!$response['ok']) {
                $lastError = $response['error'];
                continue;
            }

            if ($response['status'] >= 400) {
                AppConfig::log('Agnes HTTP ' . $response['status'] . ' for model ' . $model);
                $lastError = $this->friendlyHttpError($response['status'], $response['body'], $model);
                continue;
            }

            $decoded = json_decode($response['body'], true);
            if (!is_array($decoded)) {
                $lastError = 'Invalid response from Agnes.';
                continue;
            }

            $text = $decoded['choices'][0]['message']['content'] ?? '';
            if (!is_string($text) || trim($text) === '') {
                $text = $decoded['output_text'] ?? $decoded['content'] ?? '';
            }

            $json = $this->extractJsonObject((string) $text);
            if ($json === null) {
                $lastError = 'Agnes returned incomplete JSON.';
                continue;
            }

            $result = ProductResult::fromArray($json, 'agnes');
            if (!$result->isComplete()) {
                $lastError = 'Agnes returned an incomplete result.';
                continue;
            }

            return ['ok' => true, 'result' => $result];
        }

        return ['ok' => false, 'error' => $lastError];
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'API key or endpoint not configured'];
        }

        $rawEndpoint = $this->config->getString('agnes_endpoint');
        $endpoint = $this->normalizeChatCompletionsUrl($rawEndpoint);
        $apiKey = $this->config->getString('agnes_api_key');

        $models = $this->parseModelList(
            $this->config->getString('agnes_model', 'agnes-3.0-flash'),
            $this->config->getString('agnes_fallback_models')
        );

        if ($endpoint === '' || $this->isPlaceholderEndpoint($endpoint)) {
            return [
                'ok' => false,
                'message' => 'Invalid Agnes endpoint. Use https://apihub.agnes-ai.com/v1',
            ];
        }

        if ($models === []) {
            return ['ok' => false, 'message' => 'No Agnes model configured'];
        }

        $lastMessage = 'Connection failed';

        foreach ($models as $model) {
            $payload = [
                'model' => $model,
                'temperature' => 0,
                'max_tokens' => 32,
                'messages' => [[
                    'role' => 'user',
                    'content' => 'Reply with JSON only: {"ok":true}',
                ]],
            ];

            $response = $this->httpPostJson($endpoint, $payload, [
                'headers' => ['Authorization: Bearer ' . $apiKey],
            ]);

            if (!$response['ok']) {
                $lastMessage = $response['error'] . ' Check Agnes Endpoint URL.';
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

    /**
     * Accept base URL (/v1) or full chat/completions URL.
     */
    private function normalizeChatCompletionsUrl(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return '';
        }

        $endpoint = rtrim($endpoint, '/');

        if (str_ends_with($endpoint, '/chat/completions')) {
            return $endpoint;
        }

        if (str_ends_with($endpoint, '/v1')) {
            return $endpoint . '/chat/completions';
        }

        // Bare host like https://apihub.agnes-ai.com
        if (preg_match('#^https?://[^/]+$#i', $endpoint) === 1) {
            return $endpoint . '/v1/chat/completions';
        }

        return $endpoint . '/chat/completions';
    }

    private function isPlaceholderEndpoint(string $endpoint): bool
    {
        $host = strtolower((string) (parse_url($endpoint, PHP_URL_HOST) ?? ''));
        return $host === ''
            || str_contains($host, 'example')
            || str_contains($host, 'your-real-host')
            || str_contains($host, 'your-agnes');
    }

    private function friendlyHttpError(int $status, string $body, string $model): string
    {
        $decoded = json_decode($body, true);
        $apiMessage = '';
        if (is_array($decoded)) {
            $apiMessage = trim((string) ($decoded['error']['message'] ?? $decoded['message'] ?? ''));
        }

        if ($status === 404) {
            return 'Invalid Agnes URL or model. Use endpoint https://apihub.agnes-ai.com/v1'
                . ' and model agnes-3.0-flash.'
                . ($apiMessage !== '' ? ' (' . substr($apiMessage, 0, 120) . ')' : '');
        }

        if ($status === 401 || $status === 403) {
            return 'Invalid or unauthorized Agnes API key.'
                . ($apiMessage !== '' ? ' (' . substr($apiMessage, 0, 120) . ')' : '');
        }

        if ($status === 429) {
            return 'Agnes quota/rate limit exceeded.';
        }

        return 'Connection failed (HTTP ' . $status . ')'
            . ($apiMessage !== '' ? ': ' . substr($apiMessage, 0, 120) : '')
            . ' [model=' . $model . ']';
    }
}
