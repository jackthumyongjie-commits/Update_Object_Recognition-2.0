/**
 * Scanner page controller: camera → capture → API → AR overlay.
 * Language switch also translates recognition content (UI + result fields).
 */
(function () {
  'use strict';

  const els = {
    video: document.getElementById('cameraVideo'),
    canvas: document.getElementById('captureCanvas'),
    status: document.getElementById('statusMessage'),
    scanBtn: document.getElementById('scanButton'),
    stage: document.getElementById('cameraStage'),
    arLayer: document.getElementById('arLayer'),
    progress: document.getElementById('progressOverlay'),
    progressTitle: document.getElementById('progressTitle'),
    progressFill: document.getElementById('progressFill'),
    progressPercent: document.getElementById('progressPercent'),
    steps: document.querySelectorAll('[data-progress-step]'),
    viewfinder: document.getElementById('viewfinder'),
    reticle: document.getElementById('focusReticle'),
    torchBtn: document.getElementById('torchBtn'),
    switchBtn: document.getElementById('switchCamBtn'),
    speakBtn: document.getElementById('speakBtn'),
    replayBtn: document.getElementById('replayBtn'),
    stopVoiceBtn: document.getElementById('stopVoiceBtn'),
  };

  const apiRecognize = document.body.dataset.apiRecognize || 'api/recognize.php';
  const apiTranslate =
    document.body.dataset.apiTranslate ||
    apiRecognize.replace(/recognize\.php(?:\?.*)?$/, 'translate-result.php');

  let busy = false;
  let hasResult = false;
  let lastStatusKey = 'idle';
  let lastImageData = null;
  let lastResultData = null;
  let resultLang = null;
  let translateToken = 0;
  const resultCache = { en: null, zh: null };

  function t(key) {
    return window.I18n ? I18n.t(key) : key;
  }

  function lang() {
    return window.I18n ? I18n.lang : 'en';
  }

  function setStatus(stateKey, customText) {
    lastStatusKey = stateKey;
    const map = {
      idle: 'status_idle',
      capturing: 'status_capturing',
      processing: 'status_processing',
      lookup: 'status_lookup',
      success: 'status_success',
      failure: 'status_failure',
      translating: 'status_translating',
    };
    const text = customText || t(map[stateKey] || 'status_idle');
    els.status.textContent = text;
    els.status.dataset.state = stateKey === 'failure' ? 'error' : stateKey === 'success' ? 'success' : 'info';
  }

  function setButtonMode(mode) {
    if (mode === 'scanning') {
      els.scanBtn.disabled = true;
      els.scanBtn.textContent = t('btn_scanning');
      els.scanBtn.dataset.mode = 'scanning';
    } else if (mode === 'again') {
      els.scanBtn.disabled = false;
      els.scanBtn.textContent = t('btn_scan_again');
      els.scanBtn.dataset.mode = 'again';
    } else {
      els.scanBtn.disabled = false;
      els.scanBtn.textContent = t('btn_scan');
      els.scanBtn.dataset.mode = 'scan';
    }
    syncVoiceButtons();
  }

  function refreshButtonLabel() {
    setButtonMode(els.scanBtn.dataset.mode || 'scan');
    if (!busy && lastStatusKey && !['capturing', 'processing', 'lookup', 'translating'].includes(lastStatusKey)) {
      setStatus(lastStatusKey);
    }
  }

  function showProgress(visible) {
    els.progress.classList.toggle('is-visible', visible);
    els.progress.setAttribute('aria-hidden', visible ? 'false' : 'true');
  }

  function updateProgress(stageIndex, titleKey) {
    const percents = [20, 55, 80, 100];
    const percent = percents[stageIndex] ?? 10;
    els.progressFill.style.width = `${percent}%`;
    els.progressPercent.textContent = `${percent}%`;
    if (titleKey) {
      els.progressTitle.textContent = t(titleKey);
    }

    els.steps.forEach((step) => {
      const idx = Number(step.dataset.progressStep);
      step.classList.remove('is-done', 'is-active');
      const mark = step.querySelector('.dot');
      if (idx < stageIndex) {
        step.classList.add('is-done');
        mark.textContent = '✓';
      } else if (idx === stageIndex) {
        step.classList.add('is-active');
        mark.textContent = '●';
      } else {
        mark.textContent = '○';
      }
    });
  }

  function cameraErrorMessage(err) {
    const code = err && err.code;
    if (code === 'https') return t('camera_https');
    if (code === 'permission') return t('camera_permission');
    if (code === 'unsupported' || code === 'notfound' || code === 'error') return t('camera_unavailable');
    return err && err.message ? err.message : t('camera_unavailable');
  }

  function displayResult(data) {
    const metrics = ScannerCamera.getCoverMetrics();
    if (!metrics) {
      throw new Error(t('status_overlay_fail'));
    }
    els.viewfinder.style.opacity = '0.35';
    ArOverlay.show(data, metrics);
    hasResult = true;
    lastResultData = data;
    resultLang = lang();
    resultCache[resultLang] = data;
    setStatus('success');
    setButtonMode('again');
    if (window.Haptic) {
      Haptic.recognition();
    }
    maybeSpeak(data);
    syncVoiceButtons();
  }

  function prefs() {
    return window.DevicePrefs ? DevicePrefs.get() : {
      voiceNarration: true,
      autoSpeak: false,
      tapToFocus: true,
    };
  }

  function maybeSpeak(data) {
    const options = prefs();
    if (!window.VoiceNarration || !options.voiceNarration || !options.autoSpeak) {
      return;
    }
    if (!VoiceNarration.isSupported()) {
      return;
    }
    VoiceNarration.speakRecognitionResult(data);
  }

  function showFocusReticle(clientX, clientY) {
    if (!els.reticle || !els.stage) {
      return;
    }
    const rect = els.stage.getBoundingClientRect();
    els.reticle.style.left = (clientX - rect.left) + 'px';
    els.reticle.style.top = (clientY - rect.top) + 'px';
    els.reticle.classList.remove('is-active');
    void els.reticle.offsetWidth;
    els.reticle.classList.add('is-active');
  }

  function syncTorchButton() {
    if (!els.torchBtn) {
      return;
    }
    const supported = ScannerCamera.supportsTorch();
    els.torchBtn.hidden = !supported;
    if (!supported) {
      return;
    }
    const on = !!ScannerCamera.torchOn;
    els.torchBtn.classList.toggle('is-on', on);
    els.torchBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  function syncVoiceButtons() {
    const supported = !!(window.VoiceNarration && VoiceNarration.isSupported());
    const enabled = prefs().voiceNarration;
    const canSpeak = supported && enabled && !!lastResultData && !busy;
    [els.speakBtn, els.replayBtn, els.stopVoiceBtn].forEach((btn) => {
      if (btn) {
        btn.hidden = !supported;
      }
    });
    if (els.speakBtn) {
      els.speakBtn.disabled = !canSpeak;
    }
    if (els.replayBtn) {
      els.replayBtn.disabled = !canSpeak;
    }
  }

  async function recognizeImage(imageData) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 120000);

    const response = await fetch(apiRecognize, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ image: imageData, lang: lang() }),
      signal: controller.signal,
    });

    clearTimeout(timer);

    const payload = await response.json().catch(() => null);
    if (!payload || payload.ok !== true || !payload.data) {
      throw new Error((payload && payload.error) || t('status_failure'));
    }
    return payload.data;
  }

  function showStoredResult(data, speakNow) {
    const metrics = ScannerCamera.getCoverMetrics();
    if (!metrics) {
      return;
    }
    lastResultData = data;
    resultLang = lang();
    resultCache[resultLang] = data;
    ArOverlay.show(data, metrics);
    hasResult = true;
    setStatus('success');
    setButtonMode('again');
    syncVoiceButtons();
    if (speakNow && window.VoiceNarration && prefs().voiceNarration) {
      VoiceNarration.speakRecognitionResult(data);
    }
  }

  async function translateCurrentResult() {
    const target = lang();
    const source = lastResultData || resultCache.en || resultCache.zh;
    if (!source || busy) {
      return;
    }
    if (resultCache[target]) {
      const keepSpeaking = window.VoiceNarration && VoiceNarration.isActive();
      if (window.VoiceNarration) {
        VoiceNarration.stop();
      }
      showStoredResult(resultCache[target], keepSpeaking || prefs().autoSpeak);
      return;
    }
    if (resultLang === target) {
      return;
    }

    const keepSpeaking = window.VoiceNarration && VoiceNarration.isActive();
    if (window.VoiceNarration) {
      VoiceNarration.stop();
    }
    const token = ++translateToken;
    setStatus('translating');

    try {
      let data = null;
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 90000);
      const response = await fetch(apiTranslate, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ lang: target, data: source }),
        signal: controller.signal,
      });
      clearTimeout(timer);
      const payload = await response.json().catch(() => null);
      if (payload && payload.ok && payload.data) {
        data = Object.assign({}, source, payload.data);
      }

      if (!data && lastImageData) {
        data = await recognizeImage(lastImageData);
      }
      if (token !== translateToken || lang() !== target) {
        return;
      }
      if (!data) {
        throw new Error(t('status_translate_fail'));
      }

      showStoredResult(data, keepSpeaking || prefs().autoSpeak);
    } catch (err) {
      if (token !== translateToken || lang() !== target) {
        return;
      }
      setStatus('failure', err.message || t('status_translate_fail'));
      setButtonMode('again');
    }
  }

  async function bootCamera() {
    setStatus('idle', t('status_starting'));
    try {
      await ScannerCamera.start(els.video, els.canvas);
      syncTorchButton();
      syncVoiceButtons();
      setStatus('idle');
      setButtonMode('scan');
    } catch (err) {
      if (window.Haptic) {
        Haptic.error();
      }
      setStatus('failure', cameraErrorMessage(err));
      els.scanBtn.disabled = true;
      syncTorchButton();
    }
  }

  async function runScan() {
    if (busy) {
      return;
    }

    if (hasResult) {
      if (window.VoiceNarration) {
        VoiceNarration.stop();
      }
      ArOverlay.hide();
      hasResult = false;
      lastResultData = null;
      resultLang = null;
      resultCache.en = null;
      resultCache.zh = null;
      lastImageData = null;
      setButtonMode('scan');
      setStatus('idle');
      els.viewfinder.style.opacity = '1';
      syncVoiceButtons();
      return;
    }

    if (window.VoiceNarration) {
      VoiceNarration.unlock();
    }

    busy = true;
    setButtonMode('scanning');
    ArOverlay.hide();
    showProgress(true);
    updateProgress(0, 'progress_capturing');
    setStatus('capturing');

    let imageData;
    try {
      imageData = ScannerCamera.captureFrame(1280, 0.7);
      lastImageData = imageData;
    } catch (err) {
      showProgress(false);
      if (window.Haptic) {
        Haptic.error();
      }
      setStatus('failure', t('status_capture_fail'));
      setButtonMode('scan');
      busy = false;
      syncVoiceButtons();
      return;
    }

    updateProgress(1, 'progress_identifying');
    setStatus('processing');

    try {
      const data = await recognizeImage(imageData);

      updateProgress(2, 'progress_checking');
      setStatus('lookup');
      await wait(280);

      updateProgress(3, 'progress_showing');
      await wait(180);

      displayResult(data);
    } catch (err) {
      const msg =
        err.name === 'AbortError'
          ? t('status_timeout')
          : err.message || t('status_failure');
      if (window.Haptic) {
        Haptic.error();
      }
      setStatus('failure', msg);
      setButtonMode('scan');
      hasResult = false;
      lastResultData = null;
      resultLang = null;
      resultCache.en = null;
      resultCache.zh = null;
    } finally {
      showProgress(false);
      busy = false;
      syncVoiceButtons();
    }
  }

  function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('arResultCard') || document.querySelector('[data-ar-card]');
    ArOverlay.init(els.arLayer, card);
    els.scanBtn.addEventListener('click', runScan);
    els.stage.addEventListener('click', (e) => {
      if (!prefs().tapToFocus) {
        return;
      }
      if (e.target.closest('button, a, .ar-card, .lang-toggle, .top-bar, .bottom-bar')) {
        return;
      }
      showFocusReticle(e.clientX, e.clientY);
      if (window.Haptic) {
        Haptic.focus();
      }
      ScannerCamera.tapToFocus(e.clientX, e.clientY);
    });

    if (els.torchBtn) {
      els.torchBtn.addEventListener('click', async () => {
        try {
          const next = !ScannerCamera.torchOn;
          await ScannerCamera.setTorch(next);
          syncTorchButton();
          setStatus('idle', t(next ? 'torch_on' : 'torch_off'));
        } catch (err) {
          setStatus('failure', t(err && err.code === 'torch' ? 'torch_unsupported' : 'torch_unsupported'));
        }
      });
    }

    if (els.switchBtn) {
      els.switchBtn.addEventListener('click', async () => {
        els.switchBtn.disabled = true;
        try {
          await ScannerCamera.switchCamera();
          syncTorchButton();
          setStatus('idle', t('camera_switch_ok'));
        } catch (err) {
          const code = err && err.code;
          if (code === 'single') {
            setStatus('failure', t('camera_one'));
          } else if (code === 'permission') {
            setStatus('failure', t('camera_permission'));
          } else {
            setStatus('failure', t('camera_switch_fail'));
          }
          syncTorchButton();
        } finally {
          els.switchBtn.disabled = false;
        }
      });
    }

    if (els.speakBtn) {
      els.speakBtn.addEventListener('click', () => speakLatest(false));
    }
    if (els.replayBtn) {
      els.replayBtn.addEventListener('click', () => speakLatest(true));
    }
    if (els.stopVoiceBtn) {
      els.stopVoiceBtn.addEventListener('click', () => {
        if (window.VoiceNarration) {
          VoiceNarration.stop();
        }
      });
    }

    window.addEventListener('pagehide', releaseScanner);
    document.addEventListener('app:langchange', () => {
      refreshButtonLabel();
      if (window.I18n) I18n.apply();
      // Translate result content to match UI language
      if (hasResult && lastResultData && resultLang !== lang()) {
        translateCurrentResult();
      }
      syncVoiceButtons();
    });
    document.addEventListener('app:prefs', syncVoiceButtons);
    setStatus('idle');
    setButtonMode('scan');
    syncVoiceButtons();
    bootCamera();
  });

  function speakLatest(replay) {
    if (!window.VoiceNarration || !VoiceNarration.isSupported()) {
      setStatus('failure', t('voice_unsupported'));
      return;
    }
    if (!prefs().voiceNarration) {
      return;
    }
    if (!lastResultData) {
      setStatus('idle', t('voice_empty'));
      return;
    }
    const ok = VoiceNarration.speakRecognitionResult(lastResultData);
    if (!ok) {
      setStatus('idle', t('voice_empty'));
    }
  }

  function releaseScanner() {
    if (window.ScannerCamera) {
      ScannerCamera.stop();
    }
    if (window.VoiceNarration) {
      VoiceNarration.stop();
    }
  }
})();
