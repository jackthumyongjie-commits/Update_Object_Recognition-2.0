/**
 * Scan history list, search, pagination, details, clear.
 */
(function () {
  'use strict';

  const apiUrl = document.body.dataset.apiHistory || 'api/history.php';
  const bodyEl = document.getElementById('historyBody');
  const searchInput = document.getElementById('historySearch');
  const pageInfo = document.getElementById('pageInfo');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');
  const detail = document.getElementById('historyDetail');

  let page = 1;
  let totalPages = 1;
  let query = '';
  let lastTotal = 0;

  function t(key, vars) {
    return window.I18n ? I18n.t(key, vars) : key;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  async function load() {
    const url = `${apiUrl}?page=${page}&q=${encodeURIComponent(query)}`;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const payload = await res.json();
    if (!payload.ok) {
      bodyEl.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(payload.error || t('history_load_fail'))}</td></tr>`;
      return;
    }

    const data = payload.data;
    lastTotal = data.total || 0;
    totalPages = Math.max(1, Math.ceil(lastTotal / (data.perPage || 20)));
    pageInfo.textContent = t('history_page', {
      page: data.page,
      total: totalPages,
      count: lastTotal,
    });
    prevBtn.disabled = data.page <= 1;
    nextBtn.disabled = data.page >= totalPages;

    if (!data.items || data.items.length === 0) {
      bodyEl.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(t('history_empty'))}</td></tr>`;
      return;
    }

    bodyEl.innerHTML = data.items
      .map((item) => {
        const conf = Math.round((item.confidence || 0) * 100) + '%';
        return `<tr>
          <td>
            <strong>${escapeHtml(item.productName)}</strong><br>
            <span style="color:var(--muted);font-size:0.82rem;">${escapeHtml(item.objectLabel)}</span>
          </td>
          <td>${escapeHtml(item.provider || '—')}</td>
          <td>${escapeHtml(conf)}</td>
          <td>${escapeHtml(item.createdAt)}</td>
          <td><button type="button" class="btn" data-view="${item.id}">${escapeHtml(t('history_details'))}</button></td>
        </tr>`;
      })
      .join('');
  }

  async function showDetails(id) {
    const res = await fetch(`${apiUrl}?id=${id}`, { headers: { Accept: 'application/json' } });
    const payload = await res.json();
    if (!payload.ok) {
      detail.style.display = 'block';
      detail.textContent = payload.error || t('history_load_fail');
      return;
    }

    const d = payload.data;
    detail.style.display = 'block';
    detail.innerHTML = `
      <h2>${escapeHtml(d.productName)}</h2>
      <p class="lead">${escapeHtml(d.description || '')}</p>
      <div class="form-grid two">
        <div><strong>${escapeHtml(t('ar_manufacturer'))}</strong><div>${escapeHtml(d.manufacturer || '—')}</div></div>
        <div><strong>${escapeHtml(t('history_object'))}</strong><div>${escapeHtml(d.objectLabel || '—')}</div></div>
        <div><strong>${escapeHtml(t('ar_specification'))}</strong><div>${escapeHtml(d.specification || '—')}</div></div>
        <div><strong>${escapeHtml(t('ar_confidence'))}</strong><div>${Math.round((d.confidence || 0) * 100)}%</div></div>
        <div><strong>${escapeHtml(t('history_col_provider'))}</strong><div>${escapeHtml(d.provider || '—')}</div></div>
        <div><strong>${escapeHtml(t('history_date'))}</strong><div>${escapeHtml(d.createdAt || '—')}</div></div>
      </div>
    `;
  }

  async function clearHistory() {
    if (!confirm(t('history_clear_confirm'))) {
      return;
    }
    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'clear' }),
    });
    const payload = await res.json();
    if (!payload.ok) {
      alert(payload.error || t('history_load_fail'));
      return;
    }
    page = 1;
    detail.style.display = 'none';
    await load();
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!bodyEl) {
      return;
    }

    document.getElementById('historySearchBtn').addEventListener('click', () => {
      query = searchInput.value.trim();
      page = 1;
      load();
    });

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        query = searchInput.value.trim();
        page = 1;
        load();
      }
    });

    prevBtn.addEventListener('click', () => {
      if (page > 1) {
        page -= 1;
        load();
      }
    });

    nextBtn.addEventListener('click', () => {
      if (page < totalPages) {
        page += 1;
        load();
      }
    });

    document.getElementById('historyClearBtn').addEventListener('click', () => {
      clearHistory().catch((err) => alert(err.message));
    });

    bodyEl.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-view]');
      if (!btn) return;
      showDetails(Number(btn.dataset.view)).catch((err) => alert(err.message));
    });

    document.addEventListener('app:langchange', () => {
      load().catch(() => {});
    });

    load().catch((err) => {
      bodyEl.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(err.message)}</td></tr>`;
    });
  });
})();
