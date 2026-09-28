/**
 * Camera, voice, and haptic preferences stored in localStorage.
 * Keys match the scanner settings (cameraResolution, autoSpeak, voiceVolume, …).
 */
(function (global) {
  'use strict';

  const DEFAULTS = {
    cameraResolution: 'auto',
    cameraDeviceId: '',
    autofocus: true,
    tapToFocus: true,
    hapticFeedback: true,
    flashlight: false,
    voiceNarration: true,
    autoSpeak: true,
    voiceId: '',
    voiceVolume: 100,
  };

  const RESOLUTIONS = ['auto', 'standard', '720p', '1080p', 'high'];

  function readRaw(key) {
    try {
      return localStorage.getItem(key);
    } catch (_) {
      return null;
    }
  }

  function writeRaw(key, value) {
    try {
      localStorage.setItem(key, String(value));
    } catch (_) {
      /* private mode / blocked storage */
    }
  }

  function readBool(key, fallback) {
    const raw = readRaw(key);
    if (raw === null || raw === '') {
      return fallback;
    }
    return raw === 'true' || raw === '1';
  }

  const DevicePrefs = {
    defaults() {
      return Object.assign({}, DEFAULTS);
    },

    get() {
      const resolution = readRaw('cameraResolution') || DEFAULTS.cameraResolution;
      const rawVolume = readRaw('voiceVolume');
      let volume = rawVolume === null || rawVolume === '' ? DEFAULTS.voiceVolume : Number(rawVolume);
      if (!Number.isFinite(volume)) {
        volume = DEFAULTS.voiceVolume;
      }

      return {
        cameraResolution: RESOLUTIONS.indexOf(resolution) >= 0 ? resolution : 'auto',
        cameraDeviceId: readRaw('cameraDeviceId') || '',
        autofocus: readBool('autofocus', DEFAULTS.autofocus),
        tapToFocus: readBool('tapToFocus', DEFAULTS.tapToFocus),
        hapticFeedback: readBool('hapticFeedback', DEFAULTS.hapticFeedback),
        flashlight: readBool('flashlight', DEFAULTS.flashlight),
        voiceNarration: readBool('voiceNarration', DEFAULTS.voiceNarration),
        autoSpeak: readBool('autoSpeak', DEFAULTS.autoSpeak),
        voiceId: readRaw('voiceId') || '',
        voiceVolume: Math.max(0, Math.min(100, Math.round(volume))),
      };
    },

    set(partial) {
      const next = Object.assign({}, this.get(), partial || {});
      if (RESOLUTIONS.indexOf(next.cameraResolution) < 0) {
        next.cameraResolution = 'auto';
      }
      next.voiceVolume = Math.max(0, Math.min(100, Math.round(Number(next.voiceVolume) || 0)));

      writeRaw('cameraResolution', next.cameraResolution);
      writeRaw('cameraDeviceId', next.cameraDeviceId || '');
      writeRaw('autofocus', next.autofocus ? 'true' : 'false');
      writeRaw('tapToFocus', next.tapToFocus ? 'true' : 'false');
      writeRaw('hapticFeedback', next.hapticFeedback ? 'true' : 'false');
      writeRaw('flashlight', next.flashlight ? 'true' : 'false');
      writeRaw('voiceNarration', next.voiceNarration ? 'true' : 'false');
      writeRaw('autoSpeak', next.autoSpeak ? 'true' : 'false');
      writeRaw('voiceId', next.voiceId || '');
      writeRaw('voiceVolume', String(next.voiceVolume));

      document.dispatchEvent(new CustomEvent('app:prefs', { detail: this.get() }));
      return this.get();
    },

    reset() {
      Object.keys(DEFAULTS).forEach((key) => {
        try {
          localStorage.removeItem(key);
        } catch (_) {
          /* ignore */
        }
      });
      document.dispatchEvent(new CustomEvent('app:prefs', { detail: this.get() }));
      return this.get();
    },
  };

  global.DevicePrefs = DevicePrefs;
})(window);
