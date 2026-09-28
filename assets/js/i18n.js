/**
 * EN / 中文 UI translations (no database).
 * Usage: I18n.t('key') | data-i18n="key" | data-i18n-placeholder="key" | data-i18n-aria="key" | data-i18n-title="key"
 */
(function (global) {
  'use strict';

  const STORAGE_KEY = 'ai_ar_lang';

  const dict = {
    en: {
      brand_sub: 'Smart Object Recognition',
      lang_switch: '中文',
      lang_switch_aria: 'Switch to Chinese',
      nav_scanner: 'Scanner',
      nav_history: 'History',
      nav_settings: 'Settings',
      history_aria: 'Open scan history',
      settings_aria: 'Open settings',

      status_idle: 'Point camera at an object',
      status_starting: 'Starting camera...',
      status_capturing: 'Capturing image...',
      status_processing: 'AI identifying object...',
      status_lookup: 'Checking product information...',
      status_success: 'Object identified',
      status_failure: 'Unable to identify the object. Please try again.',
      status_timeout: 'AI request timed out. Please try again.',
      status_capture_fail: 'Unable to capture image.',
      status_overlay_fail: 'Unable to align AR overlay.',
      status_translating: 'Translating result...',
      status_translate_fail: 'Unable to translate the result.',

      btn_scan: 'Scan',
      btn_scanning: 'Scanning...',
      btn_scan_again: 'Scan Again',
      scan_aria: 'Scan object',

      progress_title: 'Identifying Object',
      progress_capture: 'Capture image',
      progress_ai: 'AI identification',
      progress_lookup: 'Product lookup',
      progress_display: 'Display result',
      progress_capturing: 'Capturing image',
      progress_identifying: 'Identifying Object',
      progress_checking: 'Checking product information',
      progress_showing: 'Display result',

      camera_https: 'Please use HTTPS to access the camera.',
      camera_unavailable: 'Camera is unavailable.',
      camera_permission: 'Camera permission was denied. Please allow camera access.',
      camera_preview: 'Live camera preview',
      camera_one: 'Only one camera is available.',
      camera_switch_fail: 'Unable to switch camera. Previous camera restored.',
      camera_switch_ok: 'Camera switched.',
      torch_unsupported: 'Flashlight is not supported on this device.',
      torch_on: 'Flashlight on',
      torch_off: 'Flashlight off',
      voice_unsupported: 'Voice narration is not supported by this browser.',
      voice_empty: 'No recognition result to read yet.',
      dock_torch: 'Flashlight',
      dock_switch: 'Switch Camera',
      dock_speak: 'Speak',
      dock_stop: 'Stop',
      dock_replay: 'Replay',
      cam_settings: 'Camera Settings',
      cam_settings_lead: 'Saved in this browser and applied the next time the scanner opens.',
      cam_resolution: 'Camera Resolution',
      res_auto: 'Auto',
      res_standard: 'Standard',
      res_720: 'HD 720p',
      res_1080: 'Full HD 1080p',
      res_high: 'High Resolution',
      cam_device: 'Camera',
      cam_device_default: 'Default camera',
      cam_device_hint: 'Names appear after the scanner has been allowed to use the camera.',
      autofocus: 'Autofocus',
      tap_focus: 'Tap to Focus',
      haptic: 'Haptic Feedback',
      haptic_unsupported: 'Haptic feedback is not supported on this device.',
      flashlight_setting: 'Flashlight',
      flashlight_hint: 'Used on the scanner when this device has a torch.',
      voice_settings: 'Voice Settings',
      voice_settings_lead: 'Reads a short summary after a successful scan. Volume and voice stay on this device.',
      voice_narration: 'Voice Narration',
      auto_speak: 'Auto Speak',
      voice_picker: 'Voice',
      voice_default: 'Browser default',
      voice_volume: 'Voice Volume',
      test_voice: 'Test Voice',
      stop_voice: 'Stop Voice',
      reset_device: 'Reset camera and voice settings',
      device_reset_ok: 'Camera and voice settings restored.',

      ar_product: 'Product',
      ar_hint_collapse: 'Tap to collapse details',
      ar_hint_expand: 'Tap for details',
      ar_manufacturer: 'Manufacturer',
      ar_specification: 'Specification',
      ar_description: 'Description',
      ar_confidence: 'Confidence',
      ar_provider: 'AI Provider',
      ar_object: 'Object',
      ar_card_aria: 'Product information. Tap to collapse or expand details.',

      settings_title: 'Settings',
      settings_lead: 'Configure AI providers securely. Keys never appear in the browser after save.',
      settings_page_title: 'Settings',
      qr_title: 'Open on Phone',
      qr_lead: 'Scan this QR code with your smartphone. Prefer HTTPS for camera access.',
      qr_hint: 'Scan this QR code using your phone.',
      qr_alt: 'QR code to open scanner',

      ai_config_title: 'AI Configuration',
      ai_config_lead: 'Choose Auto to try Gemini first, then Agnes if needed.',
      ai_provider: 'AI Provider',
      provider_auto: 'Auto',
      request_timeout: 'Request Timeout (10–120 seconds)',

      gemini_lead: 'Google Gemini Vision settings.',
      gemini_model: 'Gemini Model',
      gemini_fallback: 'Gemini Fallback Models',
      gemini_key: 'Gemini API Key',
      key_placeholder: 'Enter new key to update',
      key_saved: 'Key saved',
      key_missing: 'Not configured',
      show: 'Show',
      hide: 'Hide',
      show_key_aria: 'Show API key',
      hide_key_aria: 'Hide API key',

      agnes_lead: 'Agnes AI Vision. Use Base URL (recommended https://apihub.agnes-ai.com/v1). Fallback models are optional.',
      agnes_model: 'Agnes Model',
      agnes_fallback: 'Agnes Fallback Models',
      agnes_endpoint: 'Agnes Endpoint URL',
      agnes_key: 'Agnes API Key',

      options_title: 'Options',
      web_lookup: 'Web product lookup',
      web_lookup_hint: 'Optional refinement after AI recognition',
      history_mysql: 'Scan history (MySQL)',
      history_mysql_hint: 'Requires database setup in config.php',
      save_settings: 'Save Settings',
      test_connection: 'Test Connection',
      testing: 'Testing providers…',
      test_failed: 'Test failed',
      save_ok: 'Settings saved.',
      save_fail: 'Save failed.',
      load_fail: 'Unable to load settings.',

      history_title: 'Scan History',
      history_lead: 'Recent recognitions stored in MySQL (images are not saved).',
      history_disabled: 'Scan history is disabled. Enable it in Settings and configure MySQL in config/config.php, then import database/database.sql.',
      history_search_ph: 'Search product, label, manufacturer',
      history_search: 'Search',
      history_clear: 'Clear history',
      history_clear_confirm: 'Clear all scan history? This cannot be undone.',
      history_loading: 'Loading…',
      history_empty: 'No scan history yet.',
      history_load_fail: 'Unable to load history.',
      history_details: 'Details',
      history_prev: 'Previous',
      history_next: 'Next',
      history_page: 'Page {page} of {total} ({count} records)',
      history_col_product: 'Product',
      history_col_provider: 'Provider',
      history_col_confidence: 'Confidence',
      history_col_date: 'Date',
      history_object: 'Object',
      history_date: 'Date',
    },
    zh: {
      brand_sub: '智能物体识别',
      lang_switch: 'EN',
      lang_switch_aria: '切换到英文',
      nav_scanner: '扫描',
      nav_history: '历史',
      nav_settings: '设置',
      history_aria: '打开扫描历史',
      settings_aria: '打开设置',

      status_idle: '将摄像头对准物体',
      status_starting: '正在启动摄像头…',
      status_capturing: '正在截取画面…',
      status_processing: 'AI 正在识别物体…',
      status_lookup: '正在核对产品信息…',
      status_success: '已识别物体',
      status_failure: '无法识别该物体，请重试。',
      status_timeout: 'AI 请求超时，请重试。',
      status_capture_fail: '无法截取画面。',
      status_overlay_fail: '无法对齐 AR 标注。',
      status_translating: '正在翻译识别结果…',
      status_translate_fail: '无法翻译识别结果。',

      btn_scan: '扫描',
      btn_scanning: '扫描中…',
      btn_scan_again: '再扫一次',
      scan_aria: '扫描物体',

      progress_title: '正在识别物体',
      progress_capture: '截取图像',
      progress_ai: 'AI 识别',
      progress_lookup: '产品查询',
      progress_display: '显示结果',
      progress_capturing: '正在截取图像',
      progress_identifying: '正在识别物体',
      progress_checking: '正在核对产品信息',
      progress_showing: '显示结果',

      camera_https: '请使用 HTTPS 访问摄像头。',
      camera_unavailable: '摄像头不可用。',
      camera_permission: '摄像头权限被拒绝。请允许访问摄像头。',
      camera_preview: '实时摄像头预览',
      camera_one: '只有一个可用摄像头。',
      camera_switch_fail: '切换摄像头失败，已恢复上一个摄像头。',
      camera_switch_ok: '已切换摄像头。',
      torch_unsupported: '此设备不支持手电筒。',
      torch_on: '手电筒已打开',
      torch_off: '手电筒已关闭',
      voice_unsupported: '此浏览器不支持语音播报。',
      voice_empty: '还没有可朗读的识别结果。',
      dock_torch: '手电筒',
      dock_switch: '切换摄像头',
      dock_speak: '朗读',
      dock_stop: '停止',
      dock_replay: '重播',
      cam_settings: '摄像头设置',
      cam_settings_lead: '保存在本浏览器中，下次打开扫描页时生效。',
      cam_resolution: '摄像头分辨率',
      res_auto: '自动',
      res_standard: '标准',
      res_720: '高清 720p',
      res_1080: '全高清 1080p',
      res_high: '高分辨率',
      cam_device: '摄像头',
      cam_device_default: '默认摄像头',
      cam_device_hint: '允许扫描页使用摄像头后，这里会显示设备名称。',
      autofocus: '自动对焦',
      tap_focus: '点击对焦',
      haptic: '触感反馈',
      haptic_unsupported: '此设备不支持触感反馈。',
      flashlight_setting: '手电筒',
      flashlight_hint: '扫描页在设备支持闪光灯时才会打开。',
      voice_settings: '语音设置',
      voice_settings_lead: '识别成功后朗读简短说明。音量和声音保存在本设备。',
      voice_narration: '语音播报',
      auto_speak: '自动朗读',
      voice_picker: '声音',
      voice_default: '浏览器默认',
      voice_volume: '音量',
      test_voice: '测试语音',
      stop_voice: '停止语音',
      reset_device: '恢复摄像头和语音默认设置',
      device_reset_ok: '摄像头和语音设置已恢复。',

      ar_product: '产品',
      ar_hint_collapse: '点击收起详情',
      ar_hint_expand: '点击查看详情',
      ar_manufacturer: '制造商',
      ar_specification: '规格',
      ar_description: '描述',
      ar_confidence: '置信度',
      ar_provider: 'AI 提供商',
      ar_object: '物体',
      ar_card_aria: '产品信息。点击展开或收起详情。',

      settings_title: '设置',
      settings_lead: '安全配置 AI 提供商。保存后密钥不会显示在浏览器中。',
      settings_page_title: '设置',
      qr_title: '手机打开',
      qr_lead: '用手机扫描此二维码。摄像头建议使用 HTTPS。',
      qr_hint: '请用手机扫描此二维码。',
      qr_alt: '打开扫描页的二维码',

      ai_config_title: 'AI 配置',
      ai_config_lead: '选择 Auto 时先尝试 Gemini，失败后再用 Agnes。',
      ai_provider: 'AI 提供商',
      provider_auto: '自动',
      request_timeout: '请求超时（10–120 秒）',

      gemini_lead: 'Google Gemini Vision 设置。',
      gemini_model: 'Gemini 模型',
      gemini_fallback: 'Gemini 备用模型',
      gemini_key: 'Gemini API 密钥',
      key_placeholder: '输入新密钥以更新',
      key_saved: '密钥已保存',
      key_missing: '未配置',
      show: '显示',
      hide: '隐藏',
      show_key_aria: '显示 API 密钥',
      hide_key_aria: '隐藏 API 密钥',

      agnes_lead: 'Agnes AI Vision。Endpoint 填 Base URL 即可（推荐 https://apihub.agnes-ai.com/v1）。备用模型可留空。',
      agnes_model: 'Agnes 模型',
      agnes_fallback: 'Agnes 备用模型',
      agnes_endpoint: 'Agnes 接口地址',
      agnes_key: 'Agnes API 密钥',

      options_title: '选项',
      web_lookup: '网页产品查询',
      web_lookup_hint: '识别后可选的信息补充',
      history_mysql: '扫描历史（MySQL）',
      history_mysql_hint: '需在 config.php 中配置数据库',
      save_settings: '保存设置',
      test_connection: '测试连接',
      testing: '正在测试提供商…',
      test_failed: '测试失败',
      save_ok: '设置已保存。',
      save_fail: '保存失败。',
      load_fail: '无法加载设置。',

      history_title: '扫描历史',
      history_lead: '保存在 MySQL 中的最近识别记录（不保存原图）。',
      history_disabled: '扫描历史未启用。请在设置中开启，并在 config/config.php 配置 MySQL，然后导入 database/database.sql。',
      history_search_ph: '搜索产品、标签、制造商',
      history_search: '搜索',
      history_clear: '清空历史',
      history_clear_confirm: '确定清空全部扫描历史？此操作不可撤销。',
      history_loading: '加载中…',
      history_empty: '暂无扫描记录。',
      history_load_fail: '无法加载历史。',
      history_details: '详情',
      history_prev: '上一页',
      history_next: '下一页',
      history_page: '第 {page} / {total} 页（共 {count} 条）',
      history_col_product: '产品',
      history_col_provider: '提供商',
      history_col_confidence: '置信度',
      history_col_date: '日期',
      history_object: '物体',
      history_date: '日期',
    },
  };

  function detectDefault() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'en' || saved === 'zh') {
      return saved;
    }
    const nav = (navigator.language || 'en').toLowerCase();
    return nav.startsWith('zh') ? 'zh' : 'en';
  }

  const I18n = {
    lang: detectDefault(),

    t(key, vars) {
      const table = dict[this.lang] || dict.en;
      let text = table[key] ?? dict.en[key] ?? key;
      if (vars && typeof vars === 'object') {
        Object.keys(vars).forEach((k) => {
          text = text.replace(new RegExp('\\{' + k + '\\}', 'g'), String(vars[k]));
        });
      }
      return text;
    },

    setLang(lang) {
      this.lang = lang === 'zh' ? 'zh' : 'en';
      localStorage.setItem(STORAGE_KEY, this.lang);
      this.apply();
      document.documentElement.lang = this.lang === 'zh' ? 'zh-CN' : 'en';
      document.dispatchEvent(new CustomEvent('app:langchange', { detail: { lang: this.lang } }));
    },

    toggle() {
      this.setLang(this.lang === 'zh' ? 'en' : 'zh');
    },

    apply(root) {
      const scope = root || document;
      scope.querySelectorAll('[data-i18n]').forEach((el) => {
        const key = el.getAttribute('data-i18n');
        if (key) el.textContent = this.t(key);
      });
      scope.querySelectorAll('[data-i18n-html]').forEach((el) => {
        const key = el.getAttribute('data-i18n-html');
        if (key) el.innerHTML = this.t(key);
      });
      scope.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (key) el.setAttribute('placeholder', this.t(key));
      });
      scope.querySelectorAll('[data-i18n-aria]').forEach((el) => {
        const key = el.getAttribute('data-i18n-aria');
        if (key) el.setAttribute('aria-label', this.t(key));
      });
      scope.querySelectorAll('[data-i18n-title]').forEach((el) => {
        const key = el.getAttribute('data-i18n-title');
        if (key) el.setAttribute('title', this.t(key));
      });
      scope.querySelectorAll('[data-i18n-alt]').forEach((el) => {
        const key = el.getAttribute('data-i18n-alt');
        if (key) el.setAttribute('alt', this.t(key));
      });

      // Option labels
      scope.querySelectorAll('option[data-i18n]').forEach((el) => {
        const key = el.getAttribute('data-i18n');
        if (key) el.textContent = this.t(key);
      });
    },

    mountToggle(container) {
      if (!container) return null;
      let btn = document.getElementById('langToggleBtn') || container.querySelector('[data-lang-toggle]');
      if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lang-toggle';
        btn.id = 'langToggleBtn';
        btn.setAttribute('data-lang-toggle', '1');
        container.insertBefore(btn, container.firstChild);
      }

      const refresh = () => {
        btn.textContent = this.t('lang_switch');
        btn.setAttribute('aria-label', this.t('lang_switch_aria'));
        btn.title = this.t('lang_switch_aria');
      };
      refresh();

      if (btn.dataset.bound === '1') {
        return btn;
      }
      btn.dataset.bound = '1';

      let lastToggleAt = 0;
      const onToggle = (e) => {
        const now = Date.now();
        if (now - lastToggleAt < 400) {
          e.preventDefault();
          e.stopPropagation();
          return;
        }
        lastToggleAt = now;
        e.preventDefault();
        e.stopPropagation();
        this.toggle();
        refresh();
      };

      btn.addEventListener('click', onToggle, true);
      return btn;
    },
  };

  document.documentElement.lang = I18n.lang === 'zh' ? 'zh-CN' : 'en';
  global.I18n = I18n;

  document.addEventListener('DOMContentLoaded', () => {
    const slot =
      document.querySelector('[data-lang-slot]') ||
      document.querySelector('.page-nav') ||
      document.querySelector('.top-bar-actions') ||
      document.querySelector('.top-bar > div:last-child');
    I18n.mountToggle(slot);
    I18n.apply();
  });
})(window);
