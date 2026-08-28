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
    reader.readAsDataURL(file);
  });
});
