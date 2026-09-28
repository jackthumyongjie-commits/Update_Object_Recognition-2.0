<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }

    $body = read_json_body();
    $lang = isset($body['lang']) ? strtolower((string) $body['lang']) : 'en';
    $lang = $lang === 'zh' ? 'zh' : 'en';
    $data = $body['data'] ?? null;

    if (!is_array($data)) {
        json_response([
            'ok' => false,
            'error' => $lang === 'zh' ? '缺少识别结果数据。' : 'Missing recognition data.',
        ], 400);
    }

    $translator = new ResultTranslator(AppConfig::getInstance());
    $outcome = $translator->translate($data, $lang);

    if (!$outcome['ok']) {
        json_response(['ok' => false, 'error' => $outcome['error']], 422);
    }

    json_response([
        'ok' => true,
        'data' => $outcome['data'],
    ]);
} catch (Throwable $e) {
    AppConfig::log('translate-result.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error during translation.'], 500);
}
