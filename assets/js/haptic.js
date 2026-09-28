/**
 * Short vibration feedback. Silently skipped when the device has no vibrate API.
 */
(function (global) {
  'use strict';

  function enabled() {
    return !!(navigator.vibrate && window.DevicePrefs && DevicePrefs.get().hapticFeedback);
  }

  function pulse(pattern) {
    if (!enabled()) {
      return;
    }
    try {
      navigator.vibrate(pattern);
    } catch (_) {
      /* unsupported or blocked */
    }
  }

  const Haptic = {
    isSupported() {
      return typeof navigator.vibrate === 'function';
    },

    focus() {
      pulse(30);
    },

    recognition() {
      pulse([30, 50, 30]);
    },

    error() {
      pulse([40, 60, 40]);
    },
  };

  global.Haptic = Haptic;
})(window);
