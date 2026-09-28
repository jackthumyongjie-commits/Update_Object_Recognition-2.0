/**
 * Camera access, resolution fallback, torch, switching, and frame capture.
 * Captured frames are downscaled before they are sent to the existing AI API.
 */
(function (global) {
  'use strict';

  const PRESETS = {
    standard: { width: 640, height: 480 },
    '720p': { width: 1280, height: 720 },
    '1080p': { width: 1920, height: 1080 },
    high: { width: 2560, height: 1440 },
  };

  const FALLBACK = {
    auto: ['1080p', '720p', 'standard'],
    high: ['high', '1080p', '720p', 'standard'],
    '1080p': ['1080p', '720p', 'standard'],
    '720p': ['720p', 'standard'],
    standard: ['standard'],
  };

  const Camera = {
    stream: null,
    video: null,
    canvas: null,
    deviceId: '',
    torchOn: false,

    async start(videoEl, canvasEl) {
      this.video = videoEl;
      this.canvas = canvasEl;
      this.assertAvailable();
      this.stop();

      const prefs = this.prefs();
      this.deviceId = prefs.cameraDeviceId || '';
      this.stream = await this.acquireStream(prefs.cameraResolution, this.deviceId);
      await this.attach(this.stream);
      this.rememberDevice();
      await this.restoreEnhancements();
      return this.stream;
    },

    stop() {
      if (this.stream) {
        this.stream.getTracks().forEach((track) => track.stop());
        this.stream = null;
      }
      this.torchOn = false;
      if (this.video) {
        this.video.srcObject = null;
      }
    },

    async listCameras() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
        return [];
      }
      const devices = await navigator.mediaDevices.enumerateDevices();
      return devices
        .filter((device) => device.kind === 'videoinput' && device.deviceId)
        .map((device, index) => ({
          deviceId: device.deviceId,
          label: device.label || ('Camera ' + (index + 1)),
        }));
    },

    async switchCamera() {
      const cameras = await this.listCameras();
      if (cameras.length < 2) {
        throw Object.assign(new Error('Only one camera is available.'), { code: 'single' });
      }

      const current = this.currentDeviceId();
      let index = cameras.findIndex((camera) => camera.deviceId === current);
      if (index < 0) {
        index = 0;
      }
      const next = cameras[(index + 1) % cameras.length];
      const previous = current;
      const keepTorch = this.torchOn || this.prefs().flashlight;

      this.stop();
      try {
        this.deviceId = next.deviceId;
        this.stream = await this.acquireStream(this.prefs().cameraResolution, next.deviceId);
        await this.attach(this.stream);
        this.rememberDevice();
        if (window.DevicePrefs) {
          DevicePrefs.set({ cameraDeviceId: this.deviceId, flashlight: keepTorch });
        }
        await this.restoreEnhancements();
        return next;
      } catch (err) {
        await this.restorePrevious(previous);
        if (err && err.code === 'permission') {
          throw err;
        }
        throw Object.assign(new Error('Unable to switch camera.'), { code: 'switch', cause: err });
      }
    },

    supportsTorch() {
      const caps = this.capabilities();
      return !!(caps && caps.torch);
    },

    async setTorch(on) {
      const track = this.videoTrack();
      if (!track || !this.supportsTorch()) {
        throw Object.assign(new Error('Flashlight is not supported on this device.'), { code: 'torch' });
      }
      await track.applyConstraints({ advanced: [{ torch: !!on }] });
      this.torchOn = !!on;
      if (window.DevicePrefs) {
        DevicePrefs.set({ flashlight: this.torchOn });
      }
      return this.torchOn;
    },

    async tapToFocus(clientX, clientY) {
      const track = this.videoTrack();
      if (!track || typeof track.applyConstraints !== 'function' || !this.video) {
        return { applied: false };
      }

      const caps = this.capabilities() || {};
      const modes = Array.isArray(caps.focusMode) ? caps.focusMode : [];
      const rect = this.video.getBoundingClientRect();
      const x = clamp((clientX - rect.left) / (rect.width || 1));
      const y = clamp((clientY - rect.top) / (rect.height || 1));
      const advanced = focusConstraint(modes, caps, x, y);

      if (!advanced) {
        return { applied: false };
      }

      try {
        await track.applyConstraints({ advanced: [advanced] });
        if (advanced.focusMode !== 'continuous' && modes.indexOf('continuous') >= 0) {
          window.setTimeout(() => {
            track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
          }, 700);
        }
        return { applied: true };
      } catch (_) {
        return { applied: false };
      }
    },

    /**
     * Capture the visible frame and compress it for the existing recognizer.
     * @returns {string} JPEG data URL
     */
    captureFrame(maxDim = 1280, quality = 0.7) {
      if (!this.video || !this.canvas) {
        throw new Error('Camera is not ready.');
      }

      const video = this.video;
      const vw = video.videoWidth;
      const vh = video.videoHeight;
      if (!vw || !vh) {
        throw Object.assign(new Error('Camera frame is empty.'), { code: 'empty' });
      }

      let tw = vw;
      let th = vh;
      if (Math.max(vw, vh) > maxDim) {
        const scale = maxDim / Math.max(vw, vh);
        tw = Math.round(vw * scale);
        th = Math.round(vh * scale);
      }

      this.canvas.width = tw;
      this.canvas.height = th;
      const ctx = this.canvas.getContext('2d', { alpha: false });
      ctx.drawImage(video, 0, 0, tw, th);
      return this.canvas.toDataURL('image/jpeg', quality);
    },

    getCoverMetrics() {
      const video = this.video;
      if (!video) {
        return null;
      }

      const vw = video.videoWidth;
      const vh = video.videoHeight;
      const ew = video.clientWidth;
      const eh = video.clientHeight;
      if (!vw || !vh || !ew || !eh) {
        return null;
      }

      const videoRatio = vw / vh;
      const elementRatio = ew / eh;
      let renderW;
      let renderH;
      let offsetX;
      let offsetY;

      if (videoRatio > elementRatio) {
        renderH = eh;
        renderW = eh * videoRatio;
        offsetX = (ew - renderW) / 2;
        offsetY = 0;
      } else {
        renderW = ew;
        renderH = ew / videoRatio;
        offsetX = 0;
        offsetY = (eh - renderH) / 2;
      }

      return { vw, vh, ew, eh, renderW, renderH, offsetX, offsetY };
    },

    videoTrack() {
      if (!this.stream) {
        return null;
      }
      const tracks = this.stream.getVideoTracks();
      return tracks.length ? tracks[0] : null;
    },

    capabilities() {
      const track = this.videoTrack();
      if (!track || typeof track.getCapabilities !== 'function') {
        return null;
      }
      try {
        return track.getCapabilities();
      } catch (_) {
        return null;
      }
    },

    prefs() {
      if (window.DevicePrefs) {
        return DevicePrefs.get();
      }
      return {
        cameraResolution: 'auto',
        cameraDeviceId: '',
        autofocus: true,
        flashlight: false,
      };
    },

    assertAvailable() {
      if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        throw Object.assign(new Error('Please use HTTPS to access the camera.'), { code: 'https' });
      }
      if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
        throw Object.assign(new Error('Camera is unavailable.'), { code: 'unsupported' });
      }
    },

    async acquireStream(resolution, deviceId) {
      const chain = FALLBACK[resolution] || FALLBACK.auto;
      let activeDevice = deviceId || '';

      for (let i = 0; i < chain.length; i += 1) {
        try {
          return await navigator.mediaDevices.getUserMedia(this.constraints(chain[i], activeDevice));
        } catch (err) {
          if (isDenied(err)) {
            throw Object.assign(new Error('Camera permission was denied.'), { code: 'permission' });
          }
          if (activeDevice && isDeviceMismatch(err)) {
            activeDevice = '';
            this.deviceId = '';
            try {
              return await navigator.mediaDevices.getUserMedia(this.constraints(chain[i], ''));
            } catch (retryErr) {
              if (isDenied(retryErr)) {
                throw Object.assign(new Error('Camera permission was denied.'), { code: 'permission' });
              }
            }
          }
        }
      }

      try {
        return await navigator.mediaDevices.getUserMedia({
          audio: false,
          video: activeDevice ? { deviceId: { exact: activeDevice } } : { facingMode: { ideal: 'environment' } },
        });
      } catch (err) {
        if (isDenied(err)) {
          throw Object.assign(new Error('Camera permission was denied.'), { code: 'permission' });
        }
      }

      try {
        return await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
      } catch (err) {
        if (isDenied(err)) {
          throw Object.assign(new Error('Camera permission was denied.'), { code: 'permission' });
        }
        if (err && (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError')) {
          throw Object.assign(new Error('Camera is unavailable.'), { code: 'notfound' });
        }
        throw Object.assign(new Error('Camera is unavailable.'), { code: 'error', cause: err });
      }
    },

    constraints(preset, deviceId) {
      const video = {};
      if (deviceId) {
        video.deviceId = { exact: deviceId };
      } else {
        video.facingMode = { ideal: 'environment' };
      }
      const size = PRESETS[preset];
      if (size) {
        video.width = { ideal: size.width };
        video.height = { ideal: size.height };
      }
      return { audio: false, video };
    },

    async attach(stream) {
      this.stream = stream;
      this.video.srcObject = stream;
      this.video.setAttribute('playsinline', 'true');
      this.video.muted = true;
      await this.video.play();
    },

    currentDeviceId() {
      const track = this.videoTrack();
      if (track && typeof track.getSettings === 'function') {
        const settings = track.getSettings();
        if (settings && settings.deviceId) {
          return settings.deviceId;
        }
      }
      return this.deviceId || '';
    },

    rememberDevice() {
      const id = this.currentDeviceId();
      if (!id) {
        return;
      }
      this.deviceId = id;
      if (window.DevicePrefs) {
        const prefs = DevicePrefs.get();
        if (prefs.cameraDeviceId !== id) {
          DevicePrefs.set({ cameraDeviceId: id });
        }
      }
    },

    async restoreEnhancements() {
      const track = this.videoTrack();
      const prefs = this.prefs();
      this.torchOn = false;
      if (!track || typeof track.applyConstraints !== 'function') {
        return;
      }

      const caps = this.capabilities() || {};
      if (prefs.autofocus && Array.isArray(caps.focusMode) && caps.focusMode.indexOf('continuous') >= 0) {
        try {
          await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
        } catch (_) {
          /* focusMode is optional */
        }
      }

      if (prefs.flashlight && caps.torch) {
        try {
          await track.applyConstraints({ advanced: [{ torch: true }] });
          this.torchOn = true;
        } catch (_) {
          this.torchOn = false;
        }
      }
    },

    async restorePrevious(deviceId) {
      this.stop();
      if (!this.video) {
        return;
      }
      try {
        this.deviceId = deviceId || '';
        this.stream = await this.acquireStream(this.prefs().cameraResolution, this.deviceId);
        await this.attach(this.stream);
        this.rememberDevice();
        await this.restoreEnhancements();
      } catch (_) {
        /* leave the caller to surface the switch error */
      }
    },
  };

  function clamp(value) {
    if (!Number.isFinite(value)) {
      return 0.5;
    }
    return Math.min(1, Math.max(0, value));
  }

  function isDenied(err) {
    return !!(err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError'));
  }

  function isDeviceMismatch(err) {
    return !!(err && (
      err.name === 'OverconstrainedError' ||
      err.name === 'ConstraintNotSatisfiedError' ||
      err.name === 'NotFoundError' ||
      err.name === 'DevicesNotFoundError'
    ));
  }

  function focusConstraint(modes, caps, x, y) {
    let mode = '';
    if (modes.indexOf('single-shot') >= 0) {
      mode = 'single-shot';
    } else if (modes.indexOf('manual') >= 0) {
      mode = 'manual';
    } else if (modes.indexOf('continuous') >= 0) {
      mode = 'continuous';
    }
    if (!mode) {
      return null;
    }

    const constraint = { focusMode: mode };
    if (caps.pointsOfInterest && mode !== 'continuous') {
      constraint.pointsOfInterest = [{ x: x, y: y }];
    }
    return constraint;
  }

  global.ScannerCamera = Camera;
})(window);
