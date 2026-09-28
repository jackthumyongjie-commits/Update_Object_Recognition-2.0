<?php

declare(strict_types=1);

/**
 * Optional conservative web lookup to refine product names.
 * Never blindly replaces AI results without evidence overlap.
 */
final class ProductLookup
{
    public function __construct(private AppConfig $config)
    {
    }

    public function refine(ProductResult $result): ProductResult
    {
        if (!$this->config->getBool('web_lookup_enabled', true)) {
            return $result;
        }

        $query = trim($result->manufacturer . ' ' . $result->productName);
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;
        if (strlen($query) < 3) {
            return $result;
        }

        $url = 'https://api.duckduckgo.com/?q=' . rawurlencode($query)
            . '&format=json&no_html=1&skip_disambig=1';

        $body = $this->httpGet($url);
        if ($body === null) {
            return $result;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $result;
        }

        $heading = trim((string) ($data['Heading'] ?? ''));
        $abstract = trim((string) ($data['AbstractText'] ?? ''));

        if ($heading === '' || !$this->looksRelated($result, $heading)) {
            return $result;
        }

        if (strlen($heading) <= strlen($result->productName)) {
            return $result;
        }

        $description = $result->description;
        if ($abstract !== '' && (strlen($description) < 20 || stripos($description, $heading) === false)) {
            $description = $this->truncate($abstract, 280);
        }

        return ProductResult::fromArray([
            'objectLabel' => $result->objectLabel,
            'productName' => $heading,
            'manufacturer' => $result->manufacturer,
            'specification' => $result->specification,
            'description' => $description,
            'boundingBox' => $result->boundingBox,
            'confidence' => $result->confidence,
            'provider' => $result->provider,
        ], $result->provider);
    }

    private function looksRelated(ProductResult $result, string $heading): bool
    {
        $hay = strtolower($heading);
        $tokens = [];

        foreach ([$result->manufacturer, $result->objectLabel, $result->productName] as $part) {
            foreach (preg_split('/[\s\-_,\/]+/', strtolower($part)) ?: [] as $token) {
                $token = trim($token);
                if (strlen($token) >= 3) {
                    $tokens[] = $token;
                }
            }
        }

        $tokens = array_unique($tokens);
        if ($tokens === []) {
            return false;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (str_contains($hay, $token)) {
                $hits++;
            }
        }

        return $hits >= 1;
    }

    private function truncate(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        return rtrim(substr($text, 0, $max - 1)) . '…';
    }

    private function httpGet(string $url): ?string
    {
        $timeout = min(12, max(5, $this->config->getInt('request_timeout', 45)));

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'AI-AR-Object-Scanner/1.0',
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $status >= 400) {
                return null;
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "User-Agent: AI-AR-Object-Scanner/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        return $body === false ? null : $body;
    }
}
