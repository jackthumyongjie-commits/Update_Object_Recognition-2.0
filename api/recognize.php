<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }

    $body = read_json_body();
    $image = isset($body['image']) ? (string) $body['image'] : '';
    $lang = isset($body['lang']) ? strtolower((string) $body['lang']) : 'en';
    $lang = $lang === 'zh' ? 'zh' : 'en';

    $validator = new ImageValidator();
    $validated = $validator->validate($image);
    if (!$validated['ok']) {
        $err = $validated['error'];
        if ($lang === 'zh') {
            $map = [
                'Empty image data.' => '图像数据为空。',
                'Invalid Base64 image data.' => '无效的 Base64 图像数据。',
                'Unable to decode image data.' => '无法解码图像数据。',
                'Image is too large.' => '图像过大。',
                'Unsupported image type.' => '不支持的图像类型。',
                'Image content is invalid.' => '图像内容无效。',
            ];
            $err = $map[$err] ?? $err;
        }
        json_response(['ok' => false, 'error' => $err], 400);
    }

    $recognizer = new ObjectRecognizer();
    $outcome = $recognizer->recognize($validated['binary'], $validated['mime'], $lang);

    if (!$outcome['ok']) {
        $status = str_contains($outcome['error'], 'not configured') ? 503 : 422;
        json_response(['ok' => false, 'error' => $outcome['error']], $status);
    }

    json_response([
        'ok' => true,
        'data' => $outcome['result']->toArray(),
        'meta' => [
            'stage' => $outcome['stage'],
        ],
    ]);
} catch (Throwable $e) {
    AppConfig::log('recognize.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Server error during recognition.'], 500);
}
