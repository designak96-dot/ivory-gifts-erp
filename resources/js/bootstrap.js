import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Global confirmation prompt for any form or button carrying data-confirm.
 * This attribute was already used across the app (e.g. "Generate invoice")
 * but nothing anywhere ever implemented it — those actions were firing
 * immediately with no prompt at all. Wired here, once, globally, so every
 * current and future data-confirm element gets the same behavior without
 * needing page-specific JS.
 */
/**
 * Minimal global toast — used for non-blocking notices (e.g. "existing
 * customer found and selected") where interrupting with a browser alert()
 * or a form error would be worse UX than just letting the person continue.
 */
window.showToast = function (message, tone = 'info') {
  let container = document.getElementById('global-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'global-toast-container';
    container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.textContent = message;
  toast.style.cssText = `padding:10px 16px;border-radius:8px;font-size:13px;color:#fff;background:${tone === 'warning' ? '#a5720f' : '#1f5f56'};box-shadow:0 4px 12px rgba(0,0,0,.15)`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
};

document.addEventListener('DOMContentLoaded', () => {
  document.body.addEventListener('submit', (e) => {
    const trigger = e.submitter?.closest('[data-confirm]') || e.target.closest('[data-confirm]');
    if (trigger && !window.confirm(trigger.dataset.confirm)) {
      e.preventDefault();
    }
  });

  document.body.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-confirm]');
    if (!trigger || trigger.closest('form')) return; // form case handled by the submit listener above
    if (!window.confirm(trigger.dataset.confirm)) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  // Global double-submit protection: disables the submit button the
  // instant a real (non-cancelled) form submission goes through, so a
  // double-click or an impatient re-click while the request is in flight
  // can never fire the same create/update action twice. Applies to every
  // form in the app, not just order/quotation entry — this is a general
  // data-integrity safeguard, not a page-specific fix.
  document.body.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return; // a data-confirm cancel above already stopped this submission
    const submitBtn = e.target.querySelector('button:not([type="button"]):not([type="reset"]), input[type="submit"]');
    if (submitBtn && !submitBtn.disabled) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.textContent;
      submitBtn.textContent = 'Please wait…';
      // Safety valve: re-enable after 15s in case the request fails
      // silently or the page doesn't navigate away, so the user is never
      // permanently stuck with an unusable button.
      setTimeout(() => {
        if (submitBtn.isConnected) {
          submitBtn.disabled = false;
          if (submitBtn.dataset.originalText) submitBtn.textContent = submitBtn.dataset.originalText;
        }
      }, 15000);
    }
  });

  // Dialog close button + click-outside-to-close, for every <dialog> in
  // the app (proof viewer, quick-add customer/product, etc.) — this must
  // live here in bootstrap.js (loaded on every page) rather than
  // order-entry.js, which only loads on the Sales Order/Quotation forms.
  // The proof viewer dialog lives in the shared layout and opens from
  // Invoices/Expenses/Payments, none of which load order-entry.js — so a
  // close handler defined only there silently never attached on those
  // pages, which is exactly why the × button did nothing.
  document.body.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-close-dialog]');
    if (closeBtn) {
      closeBtn.closest('dialog')?.close();
      return;
    }
    // Click-outside-to-close: a click directly on the <dialog> element
    // itself (not on anything inside it) means the click landed on the
    // dialog's own backdrop area, since e.target is the dialog only when
    // the click didn't hit a descendant.
    if (e.target.tagName === 'DIALOG') {
      e.target.close();
    }
  });

  // Notification bell dropdown toggle, with outside-click-to-close.
  document.querySelector('[data-notif-toggle]')?.addEventListener('click', (e) => {
    e.stopPropagation();
    document.querySelector('[data-notif-dropdown]')?.toggleAttribute('hidden');
  });
  document.addEventListener('click', (e) => {
    const dropdown = document.querySelector('[data-notif-dropdown]');
    if (!dropdown || dropdown.hasAttribute('hidden')) return;
    if (!e.target.closest('.notif-bell-wrap')) dropdown.setAttribute('hidden', '');
  });

  // Global search: debounced fetch across Sales Orders, Invoices,
  // Customers, Products, Payments (server-side, permission-aware — see
  // GlobalSearchController). Reuses the existing .combo/.combo-results
  // styling (and its dropdown-clipping fix) rather than inventing new CSS.
  (() => {
    const input = document.querySelector('[data-global-search-input]');
    const resultsBox = document.querySelector('[data-global-search-results]');
    if (!input || !resultsBox) return;
    let debounce;
    input.addEventListener('input', () => {
      clearTimeout(debounce);
      const term = input.value.trim();
      if (term.length < 2) { resultsBox.innerHTML = ''; return; }
      debounce = setTimeout(async () => {
        try {
          const res = await fetch(`/search?q=${encodeURIComponent(term)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const items = res.ok ? await res.json() : [];
          resultsBox.innerHTML = items.length
            ? items.map((r) => `<div class="search-result" data-url="${r.url}"><span><b>${r.type}</b> — ${r.label}</span></div>`).join('')
            : '<div class="search-empty">No matches.</div>';
        } catch (e) {
          resultsBox.innerHTML = '';
        }
      }, 250);
    });
    resultsBox.addEventListener('mousedown', (e) => {
      const item = e.target.closest('[data-url]');
      if (!item) return;
      e.preventDefault();
      window.location.href = item.dataset.url;
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.global-search')) resultsBox.innerHTML = '';
    });
  })();

  // Theme toggle: dark is the default (set by the inline no-flash script
  // in <head>, which runs before CSS renders — this handler only needs
  // to flip it and persist the choice). Applies globally since every
  // themed rule is driven by the [data-theme="light"] attribute selector
  // on <html>, not a page-specific class.
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const html = document.documentElement;
      const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      html.setAttribute('data-theme', next);
      try { localStorage.setItem('ivory-theme', next); } catch (e) { /* storage unavailable — theme still applies for this session */ }
    });
  });

  // Mobile sidebar drawer toggle. The button (data-sidebar, both the
  // topbar hamburger and the mobile bottom-nav "More") and the CSS
  // (.sidebar.open) both already existed, but nothing in JS ever
  // connected them — a real, pre-existing bug: the mobile menu button
  // did nothing at all when tapped. Also closes on an outside tap/click
  // and on pressing Escape, matching standard drawer behavior.
  document.querySelectorAll('[data-sidebar]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      document.getElementById('sidebar')?.classList.toggle('open');
    });
  });
  document.addEventListener('click', (e) => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;
    if (sidebar.contains(e.target) || e.target.closest('[data-sidebar]')) return;
    sidebar.classList.remove('open');
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.getElementById('sidebar')?.classList.remove('open');
  });

  // Proof viewer: opens payment/expense proof in a popup dialog within
  // the same tab, instead of target="_blank" navigating to a whole new
  // browser tab. The iframe src is only set when the dialog actually
  // opens (not pre-loaded on page render) and cleared on close so a PDF
  // doesn't keep rendering in the background.
  document.body.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-view-proof]');
    if (!trigger) return;
    e.preventDefault();
    const dialog = document.getElementById('proof-viewer');
    const frame = dialog?.querySelector('[data-proof-viewer-frame]');
    if (frame) frame.src = trigger.dataset.viewProof;
    dialog?.showModal();
  });
  document.getElementById('proof-viewer')?.addEventListener('close', () => {
    const frame = document.querySelector('#proof-viewer [data-proof-viewer-frame]');
    if (frame) frame.src = 'about:blank';
  });

  // Live image preview for any [data-product-image-input] — used by both
  // the main Add/Edit Product page and the Sales Order product popup,
  // since both render the same shared products/_fields.blade.php partial.
  document.body.addEventListener('change', (e) => {
    const input = e.target.closest('[data-product-image-input]');
    if (!input) return;
    const preview = input.closest('label')?.querySelector('[data-product-image-preview]');
    if (!preview) return;
    const file = input.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      preview.innerHTML = `<img src="${reader.result}" alt="" style="max-height:80px;border-radius:8px">`;
    };
    document.querySelectorAll('[data-copy-text]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copyText);
        const original = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = original; }, 1500);
      } catch { alert('Could not copy — please copy the link manually.'); }
    });
  });

  reader.readAsDataURL(file);
  });

  // Confirmed Order Proof — drag & drop + click to browse. Works
  // wherever the widget partial is rendered (Sales Orders or
  // Deliveries, list rows or detail pages) since it's driven entirely
  // by data attributes, not page-specific IDs.
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  function wireProofWidget(widget) {
    const uploadUrl = widget.dataset.uploadUrl;
    const input = widget.querySelector('[data-proof-input]');
    const trigger = widget.querySelector('[data-proof-trigger]');
    const replaceBtn = widget.querySelector('[data-proof-replace]');
    const deleteBtn = widget.querySelector('[data-proof-delete]');

    const doUpload = async (file) => {
      if (!file) return;
      const form = new FormData();
      form.append('file', file);
      widget.classList.add('proof-uploading');
      try {
        const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, body: form });
        if (!res.ok) { const err = await res.json().catch(() => ({})); throw new Error(err.message || 'Upload failed'); }
        location.reload();
      } catch (e) {
        widget.classList.remove('proof-uploading');
        alert(e.message || 'Could not upload proof — please try again.');
      }
    };

    trigger?.addEventListener('click', () => input.click());
    replaceBtn?.addEventListener('click', (e) => { e.preventDefault(); input.click(); });
    input?.addEventListener('change', () => doUpload(input.files?.[0]));

    // Drag & drop over the whole widget.
    ['dragenter', 'dragover'].forEach((evt) => widget.addEventListener(evt, (e) => { e.preventDefault(); widget.classList.add('proof-dragover'); }));
    ['dragleave', 'drop'].forEach((evt) => widget.addEventListener(evt, (e) => { e.preventDefault(); widget.classList.remove('proof-dragover'); }));
    widget.addEventListener('drop', (e) => { const file = e.dataTransfer?.files?.[0]; if (file) doUpload(file); });

    deleteBtn?.addEventListener('click', async (e) => {
      e.preventDefault();
      const chip = e.target.closest('[data-proof-chip]');
      if (!chip || !confirm('Delete this proof? This cannot be undone.')) return;
      try {
        const res = await fetch(chip.dataset.deleteUrl, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
        if (!res.ok) throw new Error();
        location.reload();
      } catch { alert('Could not delete — please try again.'); }
    });
  }
  document.querySelectorAll('[data-proof-widget]').forEach(wireProofWidget);

  // WhatsApp share — checks Invoice/Proof state first, shows the
  // required modals, then opens the pre-filled wa.me link. Staff always
  // presses Send themselves inside WhatsApp; nothing here sends
  // anything automatically.
  function buildModal(title, message, buttons) {
    const overlay = document.createElement('div');
    overlay.className = 'wa-modal-overlay';
    const box = document.createElement('div');
    box.className = 'wa-modal-box';
    box.innerHTML = `<h3>${title}</h3><p>${message}</p><div class="wa-modal-actions"></div>`;
    const actions = box.querySelector('.wa-modal-actions');
    buttons.forEach(({ label, primary, onClick }) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn' + (primary ? ' primary' : '');
      btn.textContent = label;
      btn.addEventListener('click', () => { overlay.remove(); onClick?.(); });
      actions.appendChild(btn);
    });
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
  }

  document.querySelectorAll('[data-whatsapp-share]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try {
        const checkRes = await fetch(btn.dataset.checkUrl, { headers: { Accept: 'application/json' } });
        const check = await checkRes.json();

        if (!check.has_invoice) {
          buildModal('Invoice required', 'Invoice has not been generated for this order.', [
            { label: 'Cancel' },
            { label: 'Generate Invoice', primary: true, onClick: () => {
              const f = document.createElement('form');
              f.method = 'POST'; f.action = check.generate_invoice_url;
              f.innerHTML = `<input type="hidden" name="_token" value="${csrfToken}">`;
              document.body.appendChild(f); f.submit();
            } },
          ]);
          return;
        }

        const openWhatsApp = async () => {
          const linkRes = await fetch(btn.dataset.linkUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' } });
          if (!linkRes.ok) { alert('Could not prepare the WhatsApp message — please try again.'); return; }
          const data = await linkRes.json();
          let message = data.message;
          if (data.status === 'ready') {
            const wave = String.fromCodePoint(0x1F44B);
            const party = String.fromCodePoint(0x1F389);
            const sparkles = String.fromCodePoint(0x2728);
            const box = String.fromCodePoint(0x1F4E6);
            const down = String.fromCodePoint(0x1F447);
            const heart = String.fromCodePoint(0x1F90D);

            message = [
              `مرحبا ${data.customer_name} ${wave}`,
              '',
              `طلبك جاهز! ${party}${sparkles}`,
              '',
              `${box} *الطلب ${data.order_number}*`,
              '',
              `يمكنك مشاهدة الفاتورة وتفاصيل الطلب هنا ${down}`,
              '',
              `Hi ${data.customer_name} ${wave}`,
              '',
              `Your order is ready! ${party}${sparkles}`,
              '',
              `${box} *ORDER ${data.order_number}*`,
              '',
              `You can view your invoice & order details here ${down}`,
              '',
              data.share_url,
              '',
              `شكراً لاختيارك لنا ${heart}`,
              `Thank you for choosing us ${heart}`,
              '',
              '*Ivory Gifts*',
            ].join('\n');
          }

          const whatsappUrl = 'https://wa.me/' + data.phone + '?text=' + encodeURIComponent(message);
          window.open(whatsappUrl, '_blank');
        };

        if (!check.has_proof) {
          buildModal('Confirmed Order proof', 'Confirmed Order proof has not been uploaded.', [
            { label: 'Continue Without Proof', primary: true, onClick: openWhatsApp },
            { label: 'Upload Proof', onClick: () => { document.querySelector('[data-proof-trigger]')?.click(); } },
          ]);
          return;
        }

        await openWhatsApp();
      } catch {
        alert('Could not check order status — please try again.');
      } finally {
        btn.disabled = false;
      }
    });
  });
});
