<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $item = ScanHistory::find($id);
            if ($item === null) {
                json_response(['ok' => false, 'error' => 'Record not found.'], 404);
            }
            json_response(['ok' => true, 'data' => $item]);
        }

        $list = ScanHistory::list($search, $page, 20);
        json_response([
            'ok' => true,
            'data' => $list,
            'meta' => [
                'historyEnabled' => ScanHistory::isEnabled(),
            ],
        ]);
    }

    if ($method === 'POST') {
        $body = read_json_body();
        $action = isset($body['action']) ? (string) $body['action'] : '';

        if ($action === 'clear') {
            if (!ScanHistory::isEnabled()) {
                json_response(['ok' => false, 'error' => 'Scan history is disabled.'], 400);
            }
            ScanHistory::clear();
            json_response(['ok' => true, 'message' => 'History cleared.']);
        }

        json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
    }

    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    AppConfig::log('api/history.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unable to process history request.'], 500);
}
