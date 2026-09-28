<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

try {
    $config = AppConfig::getInstance();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        json_response([
            'ok' => true,
            'data' => $config->publicSettings(),
        ]);
    }

    if ($method === 'POST') {
        $body = read_json_body();
        $action = isset($body['action']) ? (string) $body['action'] : 'save';

        if ($action === 'test') {
            $recognizer = new ObjectRecognizer($config);
            $tests = $recognizer->testConnections();
            json_response([
                'ok' => true,
                'data' => [
                    'gemini' => [
                        'ok' => (bool) $tests['gemini']['ok'],
                        'message' => (string) $tests['gemini']['message'],
                    ],
                    'agnes' => [
                        'ok' => (bool) $tests['agnes']['ok'],
                        'message' => (string) $tests['agnes']['message'],
                    ],
                ],
            ]);
        }

        if ($action === 'save') {
            $config->saveRuntime([
                'ai_provider' => $body['ai_provider'] ?? null,
                'gemini_api_key' => $body['gemini_api_key'] ?? '',
                'gemini_model' => $body['gemini_model'] ?? null,
                'gemini_fallback_models' => $body['gemini_fallback_models'] ?? null,
                'agnes_api_key' => $body['agnes_api_key'] ?? '',
                'agnes_model' => $body['agnes_model'] ?? null,
                'agnes_fallback_models' => $body['agnes_fallback_models'] ?? null,
                'agnes_endpoint' => $body['agnes_endpoint'] ?? null,
                'request_timeout' => $body['request_timeout'] ?? null,
                'history_enabled' => $body['history_enabled'] ?? null,
                'web_lookup_enabled' => $body['web_lookup_enabled'] ?? null,
            ]);

            json_response([
                'ok' => true,
                'data' => $config->publicSettings(),
                'message' => 'Settings saved.',
            ]);
        }

        json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
    }

    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    AppConfig::log('api/settings.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unable to process settings request.'], 500);
}
