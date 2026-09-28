/**
 * Settings page: load/save config, test connections, QR code.
 */
(function () {
  'use strict';

  const apiUrl = document.body.dataset.apiSettings || 'api/settings.php';
  const scannerUrl = document.body.dataset.scannerUrl || '';

  const form = document.getElementById('settingsForm');
  const toast = document.getElementById('settingsToast');
  const testBox = document.getElementById('testResults');
  const qrImg = document.getElementById('qrImage');
  const qrLink = document.getElementById('qrLink');

  function t(key) {
    return window.I18n ? I18n.t(key) : key;
  }

  function showToast(message, ok) {
    toast.textContent = message;
    toast.className = 'toast is-visible ' + (ok ? 'ok' : 'err');
  }

  function setKeyStatus(el, configured) {
    el.textContent = configured ? t('key_saved') : t('key_missing');
    el.classList.toggle('saved', !!configured);
  }

  function applySettings(data) {
    form.ai_provider.value = data.ai_provider || 'auto';
    form.gemini_model.value = data.gemini_model || '';
    form.gemini_fallback_models.value = data.gemini_fallback_models || '';
    form.agnes_model.value = data.agnes_model || '';
    form.agnes_fallback_models.value = data.agnes_fallback_models || '';
    form.agnes_endpoint.value = data.agnes_endpoint || '';
    form.request_timeout.value = data.request_timeout || 45;
    form.history_enabled.checked = !!data.history_enabled;
    form.web_lookup_enabled.checked = !!data.web_lookup_enabled;

    setKeyStatus(document.getElementById('geminiKeyStatus'), data.gemini_key_configured);
    setKeyStatus(document.getElementById('agnesKeyStatus'), data.agnes_key_configured);

    form.gemini_api_key.value = '';
    form.agnes_api_key.value = '';
  }

  async function loadSettings() {
    const res = await fetch(apiUrl, { headers: { Accept: 'application/json' } });
    const payload = await res.json();
    if (!payload.ok) {
      throw new Error(payload.error || t('load_fail'));
    }
    applySettings(payload.data);
  }

  async function saveSettings(event) {
    event.preventDefault();
    const body = {
      action: 'save',
      ai_provider: form.ai_provider.value,
      gemini_model: form.gemini_model.value.trim(),
      gemini_fallback_models: form.gemini_fallback_models.value,
      gemini_api_key: form.gemini_api_key.value,
      agnes_model: form.agnes_model.value.trim(),
      agnes_fallback_models: form.agnes_fallback_models.value,
      agnes_endpoint: form.agnes_endpoint.value.trim(),
      agnes_api_key: form.agnes_api_key.value,
      request_timeout: Number(form.request_timeout.value || 45),
      history_enabled: form.history_enabled.checked,
      web_lookup_enabled: form.web_lookup_enabled.checked,
    };

    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    });
    const payload = await res.json();
    if (!payload.ok) {
      showToast(payload.error || t('save_fail'), false);
      return;
    }

    applySettings(payload.data);
    showToast(payload.message || t('save_ok'), true);
  }

  async function testConnection() {
    testBox.innerHTML = `<div class="test-item">${escapeHtml(t('testing'))}</div>`;
    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'test' }),
    });
    const payload = await res.json();
    if (!payload.ok) {
      testBox.innerHTML = `<div class="test-item"><span>${escapeHtml(t('test_failed'))}</span><span class="fail">${escapeHtml(payload.error || 'Error')}</span></div>`;
      return;
    }

    const g = payload.data.gemini;
    const a = payload.data.agnes;
    testBox.innerHTML = `
      <div class="test-item">
        <strong>Gemini</strong>
        <span class="${g.ok ? 'ok' : 'fail'}">${g.ok ? '✓' : '✗'} ${escapeHtml(g.message)}</span>
      </div>
      <div class="test-item">
        <strong>Agnes</strong>
        <span class="${a.ok ? 'ok' : 'fail'}">${a.ok ? '✓' : '✗'} ${escapeHtml(a.message)}</span>
      </div>
    `;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setupKeyToggle(inputId, buttonId) {
    const input = document.getElementById(inputId);
    const btn = document.getElementById(buttonId);
    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? t('hide') : t('show');
      btn.setAttribute('aria-label', show ? t('hide_key_aria') : t('show_key_aria'));
    });
  }

  function setupQr() {
    if (!scannerUrl) {
      return;
    }
    const src =
      'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' +
      encodeURIComponent(scannerUrl);
    qrImg.src = src;
    qrImg.alt = t('qr_alt');
    qrLink.href = scannerUrl;
    qrLink.textContent = scannerUrl;
  }

  document.addEventListener('DOMContentLoaded', async () => {
    setupKeyToggle('gemini_api_key', 'toggleGeminiKey');
    setupKeyToggle('agnes_api_key', 'toggleAgnesKey');
    setupQr();

    form.addEventListener('submit', (e) => {
      saveSettings(e).catch((err) => showToast(err.message, false));
    });

    document.getElementById('testConnectionBtn').addEventListener('click', () => {
      testConnection().catch((err) => showToast(err.message, false));
    });

    document.addEventListener('app:langchange', () => {
      const g = document.getElementById('geminiKeyStatus');
      const a = document.getElementById('agnesKeyStatus');
      if (g) setKeyStatus(g, g.classList.contains('saved'));
      if (a) setKeyStatus(a, a.classList.contains('saved'));
      setupQr();
    });

    try {
      await loadSettings();
    } catch (err) {
      showToast(err.message, false);
    }

    initDeviceSettings();
  });

  function initDeviceSettings() {
    if (!window.DevicePrefs) {
      return;
    }

    const resolutionInputs = document.querySelectorAll('input[name="cameraResolution"]');
    const cameraDevice = document.getElementById('cameraDevice');
    const voiceSelect = document.getElementById('voiceSelect');
    const volume = document.getElementById('voiceVolume');
    const volumeValue = document.getElementById('volumeValue');
    const voiceNote = document.getElementById('voiceSupportNote');
    const voiceFields = document.getElementById('voiceSettingsFields');
    const hapticRow = document.getElementById('hapticRow');
    const hapticHint = document.getElementById('hapticHint');
    const hapticToggle = document.getElementById('hapticToggle');

    function paint() {
      const prefs = DevicePrefs.get();
      resolutionInputs.forEach((input) => {
        input.checked = input.value === prefs.cameraResolution;
      });
      document.getElementById('autofocusToggle').checked = prefs.autofocus;
      document.getElementById('tapFocusToggle').checked = prefs.tapToFocus;
      document.getElementById('flashlightToggle').checked = prefs.flashlight;
      if (hapticToggle) {
        hapticToggle.checked = prefs.hapticFeedback;
      }
      document.getElementById('voiceNarrationToggle').checked = prefs.voiceNarration;
      document.getElementById('autoSpeakToggle').checked = prefs.autoSpeak;
      if (volume) {
        volume.value = String(prefs.voiceVolume);
      }
      if (volumeValue) {
        volumeValue.textContent = prefs.voiceVolume + '%';
      }
      if (cameraDevice) {
        const cameraMatch = Array.from(cameraDevice.options).some((opt) => opt.value === prefs.cameraDeviceId);
        cameraDevice.value = cameraMatch ? prefs.cameraDeviceId : '';
      }
      if (voiceSelect) {
        const voiceMatch = Array.from(voiceSelect.options).some((opt) => opt.value === prefs.voiceId);
        voiceSelect.value = voiceMatch ? prefs.voiceId : '';
      }
    }

    function bindToggle(id, key) {
      const input = document.getElementById(id);
      if (!input) {
        return;
      }
      input.addEventListener('change', () => {
        const patch = {};
        patch[key] = input.checked;
        DevicePrefs.set(patch);
      });
    }

    resolutionInputs.forEach((input) => {
      input.addEventListener('change', () => {
        if (input.checked) {
          DevicePrefs.set({ cameraResolution: input.value });
        }
      });
    });

    bindToggle('autofocusToggle', 'autofocus');
    bindToggle('tapFocusToggle', 'tapToFocus');
    bindToggle('flashlightToggle', 'flashlight');
    bindToggle('hapticToggle', 'hapticFeedback');
    bindToggle('voiceNarrationToggle', 'voiceNarration');
    bindToggle('autoSpeakToggle', 'autoSpeak');

    if (volume) {
      volume.addEventListener('input', () => {
        const value = Number(volume.value);
        if (volumeValue) {
          volumeValue.textContent = value + '%';
        }
        DevicePrefs.set({ voiceVolume: value });
      });
    }

    let fillingLists = false;

    if (cameraDevice) {
      cameraDevice.addEventListener('change', () => {
        if (fillingLists) {
          return;
        }
        DevicePrefs.set({ cameraDeviceId: cameraDevice.value });
      });
    }

    if (voiceSelect) {
      voiceSelect.addEventListener('change', () => {
        if (fillingLists) {
          return;
        }
        DevicePrefs.set({ voiceId: voiceSelect.value });
      });
    }

    const speechOk = window.VoiceNarration && VoiceNarration.isSupported();
    if (voiceNote) {
      voiceNote.hidden = speechOk;
    }
    if (voiceFields) {
      voiceFields.hidden = !speechOk;
    }

    const hapticOk = typeof navigator.vibrate === 'function';
    if (!hapticOk && hapticToggle) {
      hapticToggle.disabled = true;
      if (hapticHint) {
        hapticHint.hidden = false;
      }
      if (hapticRow) {
        hapticRow.classList.add('is-disabled');
      }
    }

    fillCameras();
    fillVoices();
    if (speechOk) {
      window.speechSynthesis.addEventListener('voiceschanged', fillVoices);
      document.addEventListener('app:voices', fillVoices);
    }
    document.addEventListener('app:langchange', () => {
      fillVoices();
      fillCameras();
    });
    paint();

    const testBtn = document.getElementById('testVoiceBtn');
    const stopBtn = document.getElementById('stopVoiceSettingsBtn');
    const resetBtn = document.getElementById('resetDeviceBtn');

    if (testBtn) {
      testBtn.addEventListener('click', () => {
        if (!speechOk) {
          showToast(t('voice_unsupported'), false);
          return;
        }
        VoiceNarration.unlock();
        VoiceNarration.test();
      });
    }
    if (stopBtn) {
      stopBtn.addEventListener('click', () => {
        if (window.VoiceNarration) {
          VoiceNarration.stop();
        }
      });
    }
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        DevicePrefs.reset();
        paint();
        fillVoices();
        showToast(t('device_reset_ok'), true);
      });
    }

    async function fillCameras() {
      if (!cameraDevice || !navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
        return;
      }
      const prefs = DevicePrefs.get();
      let videos = [];
      try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        videos = devices.filter((device) => device.kind === 'videoinput' && device.deviceId);
      } catch (_) {
        videos = [];
      }

      const current = prefs.cameraDeviceId;
      fillingLists = true;
      cameraDevice.innerHTML = '';
      const fallback = document.createElement('option');
      fallback.value = '';
      fallback.textContent = t('cam_device_default');
      fallback.setAttribute('data-i18n', 'cam_device_default');
      cameraDevice.appendChild(fallback);

      videos.forEach((device, index) => {
        const option = document.createElement('option');
        option.value = device.deviceId;
        option.textContent = device.label || (t('cam_device') + ' ' + (index + 1));
        cameraDevice.appendChild(option);
      });

      cameraDevice.value = current && Array.from(cameraDevice.options).some((opt) => opt.value === current)
        ? current
        : '';
      fillingLists = false;
    }

    function fillVoices() {
      if (!voiceSelect || !window.VoiceNarration) {
        return;
      }
      const prefs = DevicePrefs.get();
      const voices = VoiceNarration.listVoices();
      const current = prefs.voiceId;
      fillingLists = true;
      voiceSelect.innerHTML = '';

      const fallback = document.createElement('option');
      fallback.value = '';
      fallback.textContent = t('voice_default');
      voiceSelect.appendChild(fallback);

      voices.forEach((voice) => {
        const option = document.createElement('option');
        option.value = voice.voiceURI || voice.name;
        const langName = voice.lang ? ' (' + voice.lang + ')' : '';
        option.textContent = voice.name + langName;
        voiceSelect.appendChild(option);
      });

      const hasCurrent = current && Array.from(voiceSelect.options).some((opt) => opt.value === current);
      voiceSelect.value = hasCurrent ? current : '';
      fillingLists = false;
    }
  }
})();
