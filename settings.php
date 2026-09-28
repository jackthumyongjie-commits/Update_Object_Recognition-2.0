<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$config = AppConfig::getInstance();
$appName = $config->getString('app_name', 'AI + AR Object Scanner');
$base = app_base_path();
$asset = static fn (string $path): string => ($base !== '' ? $base : '') . '/assets/' . ltrim($path, '/');
$apiSettings = ($base !== '' ? $base : '') . '/api/settings.php';
$scannerUrl = app_public_url('index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#070b12">
  <title data-i18n="settings_page_title">Settings</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>?v=20260928d">
</head>
<body class="page"
      data-api-settings="<?= e($apiSettings) ?>"
      data-scanner-url="<?= e($scannerUrl) ?>">
  <div class="page-shell">
    <header class="page-header">
      <div class="page-title">
        <span class="brand-mark" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
        <div>
          <h1 data-i18n="settings_title">Settings</h1>
          <p class="lead" data-i18n="settings_lead">Configure AI providers securely. Keys never appear in the browser after save.</p>
        </div>
      </div>
      <nav class="page-nav" aria-label="Page navigation" data-lang-slot>
        <button type="button" class="lang-toggle" id="langToggleBtn" data-lang-toggle="1"
                data-i18n="lang_switch" data-i18n-aria="lang_switch_aria">中文</button>
        <a class="nav-link" href="<?= e(app_url('index.php')) ?>"><i class="fa-solid fa-camera" aria-hidden="true"></i> <span data-i18n="nav_scanner">Scanner</span></a>
        <a class="nav-link" href="<?= e(app_url('history.php')) ?>"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> <span data-i18n="nav_history">History</span></a>
      </nav>
    </header>

    <section class="panel">
      <h2 data-i18n="qr_title">Open on Phone</h2>
      <p class="lead" data-i18n="qr_lead">Scan this QR code with your smartphone. Prefer HTTPS for camera access.</p>
      <div class="qr-wrap">
        <img id="qrImage" src="" width="220" height="220" data-i18n-alt="qr_alt" alt="QR code">
        <a id="qrLink" href="<?= e($scannerUrl) ?>"><?= e($scannerUrl) ?></a>
        <p class="lead" style="margin:0;" data-i18n="qr_hint">Scan this QR code using your phone.</p>
      </div>
    </section>

    <form id="settingsForm" autocomplete="off">
      <section class="panel">
        <h2 data-i18n="ai_config_title">AI Configuration</h2>
        <p class="lead" data-i18n="ai_config_lead">Choose Auto to try Gemini first, then Agnes if needed.</p>
        <div class="form-grid">
          <label class="field">
            <span data-i18n="ai_provider">AI Provider</span>
            <select name="ai_provider" id="ai_provider" required>
              <option value="auto" data-i18n="provider_auto">Auto</option>
              <option value="gemini">Gemini</option>
              <option value="agnes">Agnes</option>
            </select>
          </label>
          <label class="field">
            <span data-i18n="request_timeout">Request Timeout (10–120 seconds)</span>
            <input type="number" name="request_timeout" id="request_timeout" min="10" max="120" step="1" value="45">
          </label>
        </div>
      </section>

      <section class="panel provider-panel provider-gemini" aria-labelledby="geminiHeading">
        <h2 id="geminiHeading"><i class="fa-solid fa-diamond" aria-hidden="true"></i> Gemini</h2>
        <p class="lead" data-i18n="gemini_lead">Google Gemini Vision settings.</p>
        <div class="form-grid">
          <label class="field">
            <span data-i18n="gemini_model">Gemini Model</span>
            <input type="text" name="gemini_model" id="gemini_model" placeholder="gemini-flash-latest">
          </label>
          <label class="field">
            <span data-i18n="gemini_fallback">Gemini Fallback Models</span>
            <textarea name="gemini_fallback_models" id="gemini_fallback_models" placeholder="One model per line"></textarea>
          </label>
          <label class="field">
            <span data-i18n="gemini_key">Gemini API Key</span>
            <div class="key-row">
              <input type="password" name="gemini_api_key" id="gemini_api_key" data-i18n-placeholder="key_placeholder" autocomplete="new-password">
              <button type="button" class="btn" id="toggleGeminiKey" data-i18n="show" data-i18n-aria="show_key_aria">Show</button>
            </div>
            <div class="key-status" id="geminiKeyStatus" data-i18n="key_missing">Not configured</div>
          </label>
        </div>
      </section>

      <section class="panel provider-panel provider-agnes" aria-labelledby="agnesHeading">
        <h2 id="agnesHeading"><i class="fa-solid fa-eye" aria-hidden="true"></i> Agnes</h2>
        <p class="lead" data-i18n="agnes_lead">Agnes AI Vision. Use Base URL (recommended https://apihub.agnes-ai.com/v1). Fallback models are optional.</p>
        <div class="form-grid">
          <label class="field">
            <span data-i18n="agnes_model">Agnes Model</span>
            <input type="text" name="agnes_model" id="agnes_model" placeholder="agnes-3.0-flash">
          </label>
          <label class="field">
            <span data-i18n="agnes_fallback">Agnes Fallback Models</span>
            <textarea name="agnes_fallback_models" id="agnes_fallback_models" placeholder="agnes-2.5-flash"></textarea>
          </label>
          <label class="field">
            <span data-i18n="agnes_endpoint">Agnes Endpoint URL</span>
            <input type="text" name="agnes_endpoint" id="agnes_endpoint" placeholder="https://apihub.agnes-ai.com/v1">
          </label>
          <label class="field">
            <span data-i18n="agnes_key">Agnes API Key</span>
            <div class="key-row">
              <input type="password" name="agnes_api_key" id="agnes_api_key" data-i18n-placeholder="key_placeholder" autocomplete="new-password">
              <button type="button" class="btn" id="toggleAgnesKey" data-i18n="show" data-i18n-aria="show_key_aria">Show</button>
            </div>
            <div class="key-status" id="agnesKeyStatus" data-i18n="key_missing">Not configured</div>
          </label>
        </div>
      </section>

      <section class="panel">
        <h2 data-i18n="options_title">Options</h2>
        <div class="form-grid">
          <div class="switch-row">
            <div>
              <strong data-i18n="web_lookup">Web product lookup</strong>
              <div class="key-status" data-i18n="web_lookup_hint">Optional refinement after AI recognition</div>
            </div>
            <input type="checkbox" name="web_lookup_enabled" id="web_lookup_enabled" data-i18n-aria="web_lookup">
          </div>
          <div class="switch-row">
            <div>
              <strong data-i18n="history_mysql">Scan history (MySQL)</strong>
              <div class="key-status" data-i18n="history_mysql_hint">Requires database setup in config.php</div>
            </div>
            <input type="checkbox" name="history_enabled" id="history_enabled" data-i18n-aria="history_mysql">
          </div>
          <div class="actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <span data-i18n="save_settings">Save Settings</span></button>
            <button type="button" class="btn" id="testConnectionBtn"><i class="fa-solid fa-plug" aria-hidden="true"></i> <span data-i18n="test_connection">Test Connection</span></button>
          </div>
        </div>
        <div id="settingsToast" class="toast" role="status" aria-live="polite"></div>
        <div id="testResults" class="test-results" aria-live="polite"></div>
      </section>
    </form>

    <section class="panel" id="cameraSettingsPanel">
      <h2 data-i18n="cam_settings">Camera Settings</h2>
      <p class="lead" data-i18n="cam_settings_lead">Saved in this browser and applied the next time the scanner opens.</p>
      <div class="form-grid">
        <fieldset class="res-options">
          <legend data-i18n="cam_resolution">Camera Resolution</legend>
          <label><input type="radio" name="cameraResolution" value="auto"> <span data-i18n="res_auto">Auto</span></label>
          <label><input type="radio" name="cameraResolution" value="standard"> <span data-i18n="res_standard">Standard</span></label>
          <label><input type="radio" name="cameraResolution" value="720p"> <span data-i18n="res_720">HD 720p</span></label>
          <label><input type="radio" name="cameraResolution" value="1080p"> <span data-i18n="res_1080">Full HD 1080p</span></label>
          <label><input type="radio" name="cameraResolution" value="high"> <span data-i18n="res_high">High Resolution</span></label>
        </fieldset>
        <label class="field">
          <span data-i18n="cam_device">Camera</span>
          <select id="cameraDevice"></select>
          <div class="key-status" data-i18n="cam_device_hint">Names appear after the scanner has been allowed to use the camera.</div>
        </label>
        <div class="switch-row">
          <strong data-i18n="autofocus">Autofocus</strong>
          <input type="checkbox" id="autofocusToggle" data-i18n-aria="autofocus">
        </div>
        <div class="switch-row">
          <strong data-i18n="tap_focus">Tap to Focus</strong>
          <input type="checkbox" id="tapFocusToggle" data-i18n-aria="tap_focus">
        </div>
        <div class="switch-row" id="hapticRow">
          <div>
            <strong data-i18n="haptic">Haptic Feedback</strong>
            <div class="key-status" id="hapticHint" hidden data-i18n="haptic_unsupported">Haptic feedback is not supported on this device.</div>
          </div>
          <input type="checkbox" id="hapticToggle" data-i18n-aria="haptic">
        </div>
        <div class="switch-row">
          <div>
            <strong data-i18n="flashlight_setting">Flashlight</strong>
            <div class="key-status" data-i18n="flashlight_hint">Used on the scanner when this device has a torch.</div>
          </div>
          <input type="checkbox" id="flashlightToggle" data-i18n-aria="flashlight_setting">
        </div>
      </div>
    </section>

    <section class="panel" id="voiceSettingsPanel">
      <h2 data-i18n="voice_settings">Voice Settings</h2>
      <p class="lead" data-i18n="voice_settings_lead">Reads a short summary after a successful scan. Volume and voice stay on this device.</p>
      <p class="lead" id="voiceSupportNote" hidden data-i18n="voice_unsupported">Voice narration is not supported by this browser.</p>
      <div class="form-grid" id="voiceSettingsFields">
        <div class="switch-row">
          <strong data-i18n="voice_narration">Voice Narration</strong>
          <input type="checkbox" id="voiceNarrationToggle" data-i18n-aria="voice_narration">
        </div>
        <div class="switch-row">
          <strong data-i18n="auto_speak">Auto Speak</strong>
          <input type="checkbox" id="autoSpeakToggle" data-i18n-aria="auto_speak">
        </div>
        <label class="field">
          <span data-i18n="voice_picker">Voice</span>
          <select id="voiceSelect"></select>
        </label>
        <label class="field">
          <span data-i18n="voice_volume">Voice Volume</span>
          <div class="volume-row">
            <input type="range" id="voiceVolume" min="0" max="100" value="100">
            <output id="volumeValue" for="voiceVolume">100%</output>
          </div>
        </label>
        <div class="actions">
          <button type="button" class="btn btn-primary" id="testVoiceBtn"><i class="fa-solid fa-volume-high" aria-hidden="true"></i> <span data-i18n="test_voice">Test Voice</span></button>
          <button type="button" class="btn" id="stopVoiceSettingsBtn"><i class="fa-solid fa-stop" aria-hidden="true"></i> <span data-i18n="stop_voice">Stop Voice</span></button>
          <button type="button" class="btn" id="resetDeviceBtn"><span data-i18n="reset_device">Reset camera and voice settings</span></button>
        </div>
      </div>
    </section>
  </div>

  <script src="<?= e($asset('js/i18n.js')) ?>?v=20260928d"></script>
  <script src="<?= e($asset('js/prefs.js')) ?>?v=20260928d"></script>
  <script src="<?= e($asset('js/voice.js')) ?>?v=20260928d"></script>
  <script src="<?= e($asset('js/settings.js')) ?>?v=20260928d"></script>
</body>
</html>
