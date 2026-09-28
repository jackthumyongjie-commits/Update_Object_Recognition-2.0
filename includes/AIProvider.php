<?php

declare(strict_types=1);

/**
 * Shared vision prompt and JSON extraction helpers.
 */
abstract class AIProvider
{
    protected AppConfig $config;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    abstract public function getName(): string;

    abstract public function isConfigured(): bool;

    /**
     * @return array{ok:true,result:ProductResult}|array{ok:false,error:string}
     */
    abstract public function recognize(string $imageBinary, string $mime, string $lang = 'en'): array;

    /**
     * Lightweight connectivity check without exposing secrets.
     *
     * @return array{ok:bool,message:string}
     */
    abstract public function testConnection(): array;

    protected function buildVisionPrompt(string $lang = 'en'): string
    {
        $lang = strtolower($lang) === 'zh' ? 'zh' : 'en';

        $languageRule = $lang === 'zh'
            ? "9. CRITICAL LANGUAGE RULE: Output language MUST be Simplified Chinese only.\n"
              . "   - objectLabel, productName, manufacturer, specification, description must ALL be written in 简体中文.\n"
              . "   - Example productName: \"华为 FreeBuds 充电盒\" (not English).\n"
              . "   - Example specification: \"白色、圆形、光面外壳\".\n"
              . "   - Brand Latin tokens like Huawei may appear, but the sentence/phrase must be Chinese.\n"
              . "   - Do NOT return English paragraphs."
            : "9. CRITICAL LANGUAGE RULE: Output language MUST be clear English only.\n"
              . "   - objectLabel, productName, manufacturer, specification, description must ALL be English.\n"
              . "   - Do NOT return Chinese characters in these fields.";

        return <<<PROMPT
You are a careful visual product recognition assistant.

Analyze the image and identify the MAIN physical object.

Rules:
1. Use only visual evidence from the image.
2. Read visible text, brand names, logos, model numbers, and labels when present.
3. If the exact model cannot be determined confidently, return a GENERIC product name (e.g. "Apple iPhone", "HP Laptop", "Sony headphones").
4. NEVER invent exact model numbers (e.g. do NOT guess "iPhone 15 Pro Max" or "HP EliteBook 840 G10" without clear evidence).
5. Provide manufacturer only when reasonably visible or strongly implied.
6. Provide a short useful specification and description.
7. Estimate confidence from 0 to 1.
8. Provide a bounding box for the main object using normalized coordinates from 0 to 1000 with keys ymin, xmin, ymax, xmax.
{$languageRule}

Return ONLY valid JSON with this exact shape (no markdown, no extra text):
{
  "objectLabel": "string",
  "productName": "string",
  "manufacturer": "string",
  "specification": "string",
  "description": "string",
  "boundingBox": {
    "ymin": 0,
    "xmin": 0,
    "ymax": 1000,
    "xmax": 1000
  },
  "confidence": 0.0
}
PROMPT;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractJsonObject(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Strip common markdown fences
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

        $slice = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($slice, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return list<string>
     */
    protected function parseModelList(string $primary, string $fallbackBlock): array
    {
        $models = [];
        $primary = trim($primary);
        if ($primary !== '') {
            $models[] = $primary;
        }

        foreach (preg_split('/[\r\n,]+/', $fallbackBlock) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $models, true)) {
                $models[] = $line;
            }
        }

        return $models;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{ok:true,body:string,status:int}|array{ok:false,error:string,status:int}
     */
    protected function httpPostJson(string $url, array $payload, array $options = []): array
    {
        $timeout = $this->config->getInt('request_timeout', 45);
        $timeout = max(10, min(120, $timeout));

        $headers = ['Content-Type: application/json'];
        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $h) {
                $headers[] = (string) $h;
            }
        }

        $json = json_encode($payload);
        if ($json === false) {
            return ['ok' => false, 'error' => 'Failed to encode request.', 'status' => 0];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'error' => 'Unable to start HTTP request.', 'status' => 0];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            ]);

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($body === false || $errno !== 0) {
                $msg = $errno === CURLE_OPERATION_TIMEDOUT
                    ? 'AI request timed out.'
                    : 'Network error contacting AI provider.';
                AppConfig::log($this->getName() . ' HTTP error: ' . $err);
                return ['ok' => false, 'error' => $msg, 'status' => $status];
            }

            return ['ok' => true, 'body' => (string) $body, 'status' => $status];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $json,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($body === false) {
            return ['ok' => false, 'error' => 'Network error contacting AI provider.', 'status' => $status];
        }

        return ['ok' => true, 'body' => $body, 'status' => $status];
    }
}
