/**
 * Browser speech narration for recognition results (Web Speech API).
 */
(function (global) {
  'use strict';

  const JUNK = {
    unknown: true,
    'unknown object': true,
    'n/a': true,
    na: true,
    none: true,
    null: true,
    undefined: true,
    '-': true,
    '—': true,
    '未知': true,
    '未知物体': true,
  };

  const VoiceNarration = {
    lastText: '',
    speaking: false,
    pending: 0,
    token: 0,

    isSupported() {
      return 'speechSynthesis' in window && typeof SpeechSynthesisUtterance === 'function';
    },

    /**
     * Call from a click handler so later async speech is allowed on iOS.
     */
    unlock() {
      if (!this.isSupported()) {
        return;
      }
      try {
        window.speechSynthesis.resume();
      } catch (_) {
        /* ignore */
      }
      if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
        return;
      }
      const primer = new SpeechSynthesisUtterance(' ');
      primer.volume = 0;
      primer.rate = 2;
      window.speechSynthesis.speak(primer);
    },

    uiLang() {
      return window.I18n && I18n.lang === 'zh' ? 'zh' : 'en';
    },

    isActive() {
      return this.speaking || this.pending > 0;
    },

    stop() {
      this.pending = 0;
      this.token += 1;
      this.speaking = false;
      if (!this.isSupported()) {
        return;
      }
      window.speechSynthesis.cancel();
    },

    listVoices() {
      if (!this.isSupported()) {
        return [];
      }
      const voices = window.speechSynthesis.getVoices() || [];
      const zh = this.uiLang() === 'zh';
      const preferred = voices.filter((voice) => (zh ? isChineseVoice(voice) : isEnglishVoice(voice)));
      const rest = voices.filter((voice) => preferred.indexOf(voice) < 0);
      return preferred.concat(rest);
    },

    pickVoice(voiceId) {
      const voices = this.listVoices();
      const zh = this.uiLang() === 'zh';
      const matching = voices.filter((voice) => (zh ? isChineseVoice(voice) : isEnglishVoice(voice)));
      if (voiceId && matching.length) {
        const saved = matching.find((voice) => voice.voiceURI === voiceId || voice.name === voiceId);
        if (saved) {
          return saved;
        }
      }
      return matching[0] || null;
    },

    /**
     * Each call speaks once. A tap while audio is playing queues one more reading.
     */
    speak(text) {
      if (!this.isSupported()) {
        return false;
      }
      const clean = String(text || '').replace(/\s+/g, ' ').trim();
      if (!clean) {
        return false;
      }
      this.lastText = clean;
      const synthBusy = window.speechSynthesis.speaking || window.speechSynthesis.pending;
      if (this.speaking && synthBusy) {
        this.pending += 1;
        return true;
      }
      this.speaking = false;
      this.play(clean);
      return true;
    },

    play(text) {
      const token = this.token;
      const prefs = window.DevicePrefs ? DevicePrefs.get() : { voiceVolume: 100, voiceId: '' };
      const utterance = new SpeechSynthesisUtterance(text);
      const volume = Number(prefs.voiceVolume);
      utterance.volume = Math.max(0, Math.min(1, (Number.isFinite(volume) ? volume : 100) / 100));
      utterance.rate = 1;
      utterance.pitch = 1;
      utterance.lang = this.uiLang() === 'zh' ? 'zh-CN' : 'en-US';

      const voice = this.pickVoice(prefs.voiceId);
      if (voice) {
        utterance.voice = voice;
        utterance.lang = voice.lang || utterance.lang;
      }

      utterance.onend = () => {
        if (token !== this.token) {
          return;
        }
        this.speaking = false;
        if (this.pending > 0) {
          this.pending -= 1;
          this.play(this.lastText);
        }
      };
      utterance.onerror = () => {
        if (token !== this.token) {
          return;
        }
        this.speaking = false;
        this.pending = 0;
      };

      this.speaking = true;
      try {
        window.speechSynthesis.resume();
      } catch (_) {
        /* ignore */
      }
      window.speechSynthesis.speak(utterance);
    },

    replay() {
      if (!this.lastText) {
        return false;
      }
      return this.speak(this.lastText);
    },

    test() {
      const line = this.uiLang() === 'zh'
        ? '这是人工智能物体识别系统的语音测试。'
        : 'This is a voice test for the AI Object Recognition System.';
      return this.speak(line);
    },

    /**
     * Build a short spoken summary and play it.
     * Skips JSON, URLs, ids, and empty placeholder fields.
     */
    speakRecognitionResult(result) {
      const text = this.summarize(result);
      if (!text) {
        return false;
      }
      return this.speak(text);
    },

    summarize(result) {
      if (!result || typeof result !== 'object') {
        return '';
      }

      const product = useful(result.productName) || useful(result.objectLabel);
      const maker = useful(result.manufacturer);
      const description = useful(result.description);
      const spec = useful(result.specification);
      const label = useful(result.objectLabel);
      const zh = window.I18n && I18n.lang === 'zh';
      const sentences = [];

      if (zh) {
        if (product && maker && product.indexOf(maker) === -1) {
          sentences.push('这是' + maker + '的' + product + '。');
        } else if (product) {
          sentences.push('这是' + product + '。');
        }
        if (description) {
          sentences.push(finish(description, '。'));
        } else if (spec) {
          sentences.push(finish(spec, '。'));
        }
        return sentences.join('');
      }

      if (product) {
        let subject = product;
        if (maker && product.toLowerCase().indexOf(maker.toLowerCase()) === -1) {
          subject = maker + ' ' + product;
        }
        sentences.push('This is ' + article(subject) + '.');
      }

      if (description) {
        sentences.push(finish(description, '.'));
      } else if (spec && spec.toLowerCase() !== String(product || '').toLowerCase()) {
        sentences.push('It is ' + spec.replace(/[.。]+$/, '') + '.');
      } else if (label && product && label.toLowerCase() !== product.toLowerCase()) {
        sentences.push('It is ' + article(label) + '.');
      }

      return sentences.join(' ');
    },
  };

  function isEnglishVoice(voice) {
    return /^en/i.test(voice.lang || '');
  }

  function isChineseVoice(voice) {
    return /^(zh|cmn|yue)/i.test(voice.lang || '');
  }

  function useful(value) {
    const text = String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
    if (!text || JUNK[text.toLowerCase()]) {
      return '';
    }
    if (/^https?:\/\//i.test(text) || /^[{[]/.test(text)) {
      return '';
    }
    return text.length > 360 ? text.slice(0, 360) : text;
  }

  function article(name) {
    return (/^[aeiou]/i.test(name) ? 'an ' : 'a ') + name;
  }

  function finish(text, mark) {
    return /[.。!！?？]$/.test(text) ? text : text + mark;
  }

  if (VoiceNarration.isSupported()) {
    window.speechSynthesis.addEventListener('voiceschanged', () => {
      document.dispatchEvent(new CustomEvent('app:voices'));
    });
  }

  global.VoiceNarration = VoiceNarration;
  global.speakRecognitionResult = function speakRecognitionResult(result) {
    return VoiceNarration.speakRecognitionResult(result);
  };
})(window);
