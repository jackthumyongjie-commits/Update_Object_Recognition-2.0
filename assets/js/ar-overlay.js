/**
 * Lightweight AR overlay: detection box + fixed top info card.
 */
(function (global) {
  'use strict';

  const ArOverlay = {
    layer: null,
    box: null,
    boxLabel: null,
    card: null,
    titleEl: null,
    details: null,
    visible: false,

    init(layerEl, cardEl) {
      this.layer = layerEl;
      this.box = layerEl.querySelector('[data-ar-box]');
      this.boxLabel = layerEl.querySelector('[data-ar-box-label]');
      this.card = cardEl || document.querySelector('[data-ar-card]');
      this.titleEl = this.card.querySelector('[data-ar-title]');
      this.details = {
        manufacturer: this.card.querySelector('[data-ar-manufacturer]'),
        specification: this.card.querySelector('[data-ar-specification]'),
        description: this.card.querySelector('[data-ar-description]'),
        confidence: this.card.querySelector('[data-ar-confidence]'),
        provider: this.card.querySelector('[data-ar-provider]'),
      };

      this.card.addEventListener('click', () => this.toggleExpand());
      this.card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.toggleExpand();
        }
      });
    },

    hide() {
      this.visible = false;
      this.box.classList.remove('is-visible');
      this.card.classList.remove('is-visible', 'is-expanded');
      this.box.style.visibility = 'hidden';
      this.card.style.visibility = 'hidden';
      this.clearCardPosition();
      this.card.scrollTop = 0;
    },

    clearCardPosition() {
      // Keep card fixed at top; never inherit old bottom offsets.
      this.card.style.position = 'fixed';
      this.card.style.left = '12px';
      this.card.style.right = '12px';
      this.card.style.top = 'calc(64px + env(safe-area-inset-top, 0px))';
      this.card.style.bottom = 'auto';
      this.card.style.width = 'auto';
      this.card.style.maxHeight = 'min(30dvh, 230px)';
      this.card.style.zIndex = '35';
    },

    toggleExpand() {
      if (!this.visible) {
        return;
      }
      this.card.classList.toggle('is-expanded');
      const hint = this.card.querySelector('[data-ar-hint]');
      if (hint && window.I18n) {
        hint.textContent = this.card.classList.contains('is-expanded')
          ? I18n.t('ar_hint_collapse')
          : I18n.t('ar_hint_expand');
      }
    },

    show(result, metrics) {
      if (!result || !result.boundingBox || !metrics || !this.card) {
        return;
      }

      const bb = result.boundingBox;
      const left = metrics.offsetX + (bb.xmin / 1000) * metrics.renderW;
      const top = metrics.offsetY + (bb.ymin / 1000) * metrics.renderH;
      const width = Math.max(40, ((bb.xmax - bb.xmin) / 1000) * metrics.renderW);
      const height = Math.max(40, ((bb.ymax - bb.ymin) / 1000) * metrics.renderH);

      this.box.style.visibility = 'visible';
      this.box.style.left = `${left}px`;
      this.box.style.top = `${top}px`;
      this.box.style.width = `${width}px`;
      this.box.style.height = `${height}px`;
      this.boxLabel.textContent = result.objectLabel || 'Object';
      this.box.classList.add('is-visible');

      this.titleEl.textContent = result.productName || result.objectLabel || (window.I18n ? I18n.t('ar_product') : 'Product');
      this.details.manufacturer.textContent = result.manufacturer || '—';
      this.details.specification.textContent = result.specification || '—';
      this.details.description.textContent = result.description || '—';
      this.details.confidence.textContent = `${Math.round((result.confidence || 0) * 100)}%`;
      this.details.provider.textContent = this.formatProvider(result.provider);

      const hint = this.card.querySelector('[data-ar-hint]');
      if (hint && window.I18n) {
        hint.textContent = I18n.t('ar_hint_collapse');
      }

      this.clearCardPosition();
      this.card.style.visibility = 'visible';
      this.card.classList.add('is-visible', 'is-expanded');
      this.card.scrollTop = 0;
      if (window.I18n) {
        this.card.setAttribute('aria-label', I18n.t('ar_card_aria'));
      }

      this.visible = true;
    },

    formatProvider(name) {
      if (!name) {
        return '—';
      }
      const n = String(name).toLowerCase();
      if (n === 'gemini') return 'Gemini';
      if (n === 'agnes') return 'Agnes';
      return name;
    },
  };

  global.ArOverlay = ArOverlay;
})(window);
