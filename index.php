<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$config = AppConfig::getInstance();
$appName = $config->getString('app_name', 'AI + AR Object Scanner');
$base = app_base_path();
$v = '20260928e'; // cache bust for mobile browsers
$asset = static fn (string $path): string => ($base !== '' ? $base : '') . '/assets/' . ltrim($path, '/') . '?v=' . $v;
$apiRecognize = ($base !== '' ? $base : '') . '/api/recognize.php';
$apiTranslate = ($base !== '' ? $base : '') . '/api/translate-result.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#070b12">
  <title><?= e($appName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-grid.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
</head>
<body class="scanner-body"
      data-api-recognize="<?= e($apiRecognize) ?>"
      data-api-translate="<?= e($apiTranslate) ?>">
  <main class="scanner-app" role="application" aria-label="<?= e($appName) ?>">
    <div class="camera-stage" id="cameraStage">
      <video id="cameraVideo" autoplay playsinline muted data-i18n-aria="camera_preview"></video>
      <canvas id="captureCanvas" class="camera-canvas" aria-hidden="true"></canvas>

      <div class="focus-reticle" id="focusReticle" aria-hidden="true">
        <span class="focus-bracket tl"></span>
        <span class="focus-bracket tr"></span>
        <span class="focus-bracket bl"></span>
        <span class="focus-bracket br"></span>
        <span class="focus-dot"></span>
      </div>

      <div class="viewfinder" id="viewfinder" aria-hidden="true">
        <div class="scan-frame">
          <div class="scan-corners">
            <span></span>
            <span></span>
          </div>
          <div class="scan-line"></div>
        </div>
      </div>

      <div class="ar-layer" id="arLayer" aria-live="polite">
        <div class="ar-box" data-ar-box style="visibility:hidden">
          <span class="ar-box-label" data-ar-box-label>Object</span>
        </div>
      </div>
    </div>

    <!-- Fixed top result card (outside camera layer so it never sinks behind bottom UI) -->
    <article class="ar-card" id="arResultCard" data-ar-card role="button" tabindex="0"
             data-i18n-aria="ar_card_aria"
             style="visibility:hidden">
      <h2 data-ar-title>Product</h2>
      <p class="hint" data-ar-hint data-i18n="ar_hint_collapse">Tap to collapse details</p>
      <div class="ar-card-details">
        <div class="detail-row">
          <dt data-i18n="ar_manufacturer">Manufacturer</dt>
          <dd data-ar-manufacturer>—</dd>
        </div>
        <div class="detail-row">
          <dt data-i18n="ar_specification">Specification</dt>
          <dd data-ar-specification>—</dd>
        </div>
        <div class="detail-row">
          <dt data-i18n="ar_description">Description</dt>
          <dd data-ar-description>—</dd>
        </div>
        <div class="detail-row">
          <dt data-i18n="ar_confidence">Confidence</dt>
          <dd data-ar-confidence>—</dd>
        </div>
        <div class="detail-row">
          <dt data-i18n="ar_provider">AI Provider</dt>
          <dd data-ar-provider>—</dd>
        </div>
      </div>
    </article>

    <header class="top-bar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-cube"></i></span>
        <span class="brand-copy">
          <strong><?= e($appName) ?></strong>
          <span data-i18n="brand_sub">Smart Object Recognition</span>
        </span>
      </div>
      <div class="top-bar-actions" data-lang-slot>
        <button type="button" class="lang-toggle" id="langToggleBtn" data-lang-toggle="1"
                data-i18n="lang_switch" data-i18n-aria="lang_switch_aria">中文</button>
        <a class="icon-btn" href="<?= e(app_url('history.php')) ?>" data-i18n-aria="history_aria" data-i18n-title="nav_history">
          <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
        </a>
        <a class="icon-btn" href="<?= e(app_url('settings.php')) ?>" data-i18n-aria="settings_aria" data-i18n-title="nav_settings">
          <i class="fa-solid fa-gear" aria-hidden="true"></i>
        </a>
      </div>
    </header>

    <footer class="bottom-bar">
      <div class="status-pill" id="statusMessage" role="status" aria-live="polite">
        Point camera at an object
      </div>
      <div class="cam-dock" id="camDock">
        <button type="button" class="dock-btn" id="torchBtn" hidden aria-pressed="false" data-i18n-aria="dock_torch">
          <i class="fa-solid fa-bolt" aria-hidden="true"></i>
          <span data-i18n="dock_torch">Flashlight</span>
        </button>
        <button type="button" class="dock-btn" id="switchCamBtn" data-i18n-aria="dock_switch">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          <span data-i18n="dock_switch">Switch Camera</span>
        </button>
        <button type="button" class="dock-btn" id="speakBtn" disabled data-i18n-aria="dock_speak">
          <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
          <span data-i18n="dock_speak">Speak</span>
        </button>
        <button type="button" class="dock-btn" id="replayBtn" disabled data-i18n-aria="dock_replay">
          <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
          <span data-i18n="dock_replay">Replay</span>
        </button>
        <button type="button" class="dock-btn" id="stopVoiceBtn" data-i18n-aria="dock_stop">
          <i class="fa-solid fa-stop" aria-hidden="true"></i>
          <span data-i18n="dock_stop">Stop</span>
        </button>
      </div>
      <button type="button" class="scan-btn" id="scanButton" data-mode="scan" data-i18n-aria="scan_aria">
        Scan
      </button>
    </footer>

    <div class="progress-overlay" id="progressOverlay" aria-hidden="true">
      <div class="progress-panel" role="dialog" aria-labelledby="progressTitle">
        <h2 id="progressTitle" data-i18n="progress_title">Identifying Object</h2>
        <div class="progress-track" aria-hidden="true">
          <div class="progress-fill" id="progressFill"></div>
        </div>
        <p class="progress-percent" id="progressPercent">0%</p>
        <ol class="progress-steps">
          <li data-progress-step="0"><span class="dot" aria-hidden="true">○</span> <span data-i18n="progress_capture">Capture image</span></li>
          <li data-progress-step="1"><span class="dot" aria-hidden="true">○</span> <span data-i18n="progress_ai">AI identification</span></li>
          <li data-progress-step="2"><span class="dot" aria-hidden="true">○</span> <span data-i18n="progress_lookup">Product lookup</span></li>
          <li data-progress-step="3"><span class="dot" aria-hidden="true">○</span> <span data-i18n="progress_display">Display result</span></li>
        </ol>
      </div>
    </div>
  </main>

  <script src="<?= e($asset('js/i18n.js')) ?>"></script>
  <script src="<?= e($asset('js/prefs.js')) ?>"></script>
  <script src="<?= e($asset('js/haptic.js')) ?>"></script>
  <script src="<?= e($asset('js/voice.js')) ?>"></script>
  <script src="<?= e($asset('js/camera.js')) ?>"></script>
  <script src="<?= e($asset('js/ar-overlay.js')) ?>"></script>
  <script src="<?= e($asset('js/scanner.js')) ?>"></script>
</body>
</html>
