<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$config = AppConfig::getInstance();
$appName = $config->getString('app_name', 'AI + AR Object Scanner');
$base = app_base_path();
$asset = static fn (string $path): string => ($base !== '' ? $base : '') . '/assets/' . ltrim($path, '/');
$apiHistory = ($base !== '' ? $base : '') . '/api/history.php';
$historyEnabled = ScanHistory::isEnabled();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#070b12">
  <title data-i18n="history_title">Scan History</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>?v=20260928d">
</head>
<body class="page" data-api-history="<?= e($apiHistory) ?>">
  <div class="page-shell">
    <header class="page-header">
      <div class="page-title">
        <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
        <div>
          <h1 data-i18n="history_title">Scan History</h1>
          <p class="lead" data-i18n="history_lead">Recent recognitions stored in MySQL (images are not saved).</p>
        </div>
      </div>
      <nav class="page-nav" aria-label="Page navigation" data-lang-slot>
        <button type="button" class="lang-toggle" id="langToggleBtn" data-lang-toggle="1"
                data-i18n="lang_switch" data-i18n-aria="lang_switch_aria">中文</button>
        <a class="nav-link" href="<?= e(app_url('index.php')) ?>"><i class="fa-solid fa-camera" aria-hidden="true"></i> <span data-i18n="nav_scanner">Scanner</span></a>
        <a class="nav-link" href="<?= e(app_url('settings.php')) ?>"><i class="fa-solid fa-gear" aria-hidden="true"></i> <span data-i18n="nav_settings">Settings</span></a>
      </nav>
    </header>

    <section class="panel">
      <?php if (!$historyEnabled): ?>
        <div class="empty-state" data-i18n="history_disabled">
          Scan history is disabled. Enable it in Settings and configure MySQL in config/config.php, then import database/database.sql.
        </div>
      <?php else: ?>
        <div class="history-toolbar">
          <input type="search" id="historySearch" data-i18n-placeholder="history_search_ph" data-i18n-aria="history_search">
          <button type="button" class="btn" id="historySearchBtn"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <span data-i18n="history_search">Search</span></button>
          <button type="button" class="btn btn-danger" id="historyClearBtn"><i class="fa-solid fa-trash" aria-hidden="true"></i> <span data-i18n="history_clear">Clear history</span></button>
        </div>

        <div class="table-wrap">
          <table class="history-table" aria-label="Scan history table">
            <thead>
              <tr>
                <th data-i18n="history_col_product">Product</th>
                <th data-i18n="history_col_provider">Provider</th>
                <th data-i18n="history_col_confidence">Confidence</th>
                <th data-i18n="history_col_date">Date</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="historyBody">
              <tr><td colspan="5" class="empty-state" data-i18n="history_loading">Loading…</td></tr>
            </tbody>
          </table>
        </div>

        <div class="pager">
          <button type="button" class="btn" id="prevPage" disabled data-i18n="history_prev">Previous</button>
          <span id="pageInfo" aria-live="polite">Page 1</span>
          <button type="button" class="btn" id="nextPage" disabled data-i18n="history_next">Next</button>
        </div>

        <div id="historyDetail" class="panel" style="display:none;margin-top:16px;margin-bottom:0;" aria-live="polite"></div>
      <?php endif; ?>
    </section>
  </div>

  <script src="<?= e($asset('js/i18n.js')) ?>?v=20260923b"></script>
  <?php if ($historyEnabled): ?>
  <script src="<?= e($asset('js/history.js')) ?>?v=20260923b"></script>
  <?php endif; ?>
</body>
</html>
