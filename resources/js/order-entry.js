/**
 * Shared line-item entry interactivity for both the Sales Order form and
 * the Quotation form (one file, one behavior, per the explicit instruction
 * not to maintain a different older product-entry system in Quotations).
 *
 * Combobox pattern for both Customer and Product: ONE visible text input
 * that doubles as the search box AND the display of whatever is currently
 * selected — no separate "chip" shown elsewhere, no second native <select>
 * visible in the UI. A hidden input carries the real ID for form
 * submission. Typing again after something is selected clears the hidden
 * ID and starts a fresh search, exactly like a normal dropdown.
 *
 * Naming discipline learned the hard way across two earlier collision bugs
 * in this file: search-result payload fields are always read under
 * data-result-* names, distinct from data-price/data-tax used elsewhere,
 * and the old "all products pre-rendered as <option> catalog" approach is
 * gone entirely — there is no longer a second DOM element that could ever
 * be matched by the same attribute name as a real input.
 */
document.addEventListener('DOMContentLoaded', () => {
  // Quotation -> Sales Order conversion modal preview runs independently —
  // it appears on the quotation SHOW page, which has no [data-order-form]/
  // [data-quotation-form] at all, so this must not depend on that guard.
  const convertDialog = document.getElementById('convert-to-order');
  const convertDateInput = convertDialog?.querySelector('[data-convert-order-date]');
  const convertRefInput = convertDialog?.querySelector('[data-convert-manual-reference]');
  const convertFinalOutput = convertDialog?.querySelector('[data-convert-final-number]');
  function updateConvertPreview() {
    if (!convertFinalOutput || !convertRefInput || !convertDateInput) return;
    const manual = convertRefInput.value.trim().toUpperCase();
    const dateVal = convertDateInput.value;
    if (!manual || !dateVal) { convertFinalOutput.textContent = '—'; return; }
    const [yyyy, mm] = dateVal.split('-');
    convertFinalOutput.textContent = `${manual}-${mm}${yyyy.slice(2)}`;
  }
  convertRefInput?.addEventListener('input', updateConvertPreview);
  convertDateInput?.addEventListener('change', updateConvertPreview);

  const form = document.querySelector('[data-order-form], [data-quotation-form]');
  if (!form) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_token"]')?.value;
  const productSearchUrl = form.dataset.productSearchUrl;
  const customerSearchUrl = form.dataset.customerSearchUrl;

  const itemsTable = document.querySelector('#order-items, #quote-items');
  const rowTemplate = itemsTable?.querySelector('template[data-row-template]');
  const manualRowTemplate = itemsTable?.querySelector('template[data-manual-row-template]');
  let rowIndex = itemsTable ? itemsTable.querySelectorAll('tbody tr[data-product-row]').length : 0;

  // ---------- live "Final order number" preview (MANUAL-MMYY, orders only) ----------
  const manualRefInput = document.querySelector('[data-manual-reference]');
  const orderDateInput = document.querySelector('[data-order-date]');
  const finalNumberOutput = document.querySelector('[data-final-order-number]');
  function updateFinalOrderNumber() {
    if (!finalNumberOutput || !manualRefInput || !orderDateInput) return;
    const manual = manualRefInput.value.trim().toUpperCase();
    const dateVal = orderDateInput.value;
    if (!manual || !dateVal) { finalNumberOutput.textContent = '—'; return; }
    const [yyyy, mm] = dateVal.split('-');
    finalNumberOutput.textContent = `${manual}-${mm}${yyyy.slice(2)}`;
  }
  manualRefInput?.addEventListener('input', updateFinalOrderNumber);
  orderDateInput?.addEventListener('change', updateFinalOrderNumber);
  updateFinalOrderNumber();

  // ---------- line totals ----------
  function recalcRow(row) {
    const qty = parseFloat(row.querySelector('[data-qty]')?.value || 0);
    const price = parseFloat(row.querySelector('[data-price]')?.value || 0);
    const discount = parseFloat(row.querySelector('[data-discount]')?.value || 0);
    const taxRate = parseFloat(row.querySelector('[data-tax]')?.value || 0);
    const lineBase = Math.max(0, qty * price - discount);
    const lineTax = lineBase * (taxRate / 100);
    const lineTotal = lineBase + lineTax;
    const totalCell = row.querySelector('[data-line-total]');
    if (totalCell) totalCell.textContent = lineTotal.toFixed(2);
    return { subtotal: qty * price, discount, tax: lineTax };
  }

  function recalcAll() {
    if (!itemsTable) return;
    let subtotal = 0, discountTotal = 0, tax = 0;
    itemsTable.querySelectorAll('tbody tr[data-product-row]').forEach((row) => {
      const r = recalcRow(row);
      subtotal += r.subtotal;
      discountTotal += r.discount;
      tax += r.tax;
    });
    const grand = subtotal - discountTotal + tax;
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.textContent = val.toFixed(2); };
    set('[data-subtotal]', subtotal);
    set('[data-discount-total]', discountTotal);
    set('[data-tax-total]', tax);
    set('[data-grand-total]', grand);
  }

  // ---------- generic combobox builder ----------
  function wireCombo({ visibleInput, hiddenIdInput, resultsBox, searchUrl, renderResult, onSelect, onClear }) {
    if (!visibleInput || !searchUrl) return;
    let timer;
    let selected = !!hiddenIdInput?.value;

    visibleInput.addEventListener('focus', () => {
      if (selected) visibleInput.select();
    });

    visibleInput.addEventListener('input', () => {
      if (selected) {
        selected = false;
        if (hiddenIdInput) hiddenIdInput.value = '';
        onClear?.();
      }
      clearTimeout(timer);
      const term = visibleInput.value.trim();
      if (!resultsBox) return;
      if (term.length < 2) { resultsBox.innerHTML = ''; return; }
      timer = setTimeout(async () => {
        const res = await fetch(`${searchUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
        const results = await res.json();
        resultsBox.innerHTML = results.map(renderResult).join('')
          || '<div class="search-empty">No match.</div>';
      }, 250);
    });

    resultsBox?.addEventListener('mousedown', (e) => {
      e.preventDefault();
      const item = e.target.closest('[data-id]');
      if (!item) return;
      resultsBox.innerHTML = '';
      if (hiddenIdInput) hiddenIdInput.value = item.dataset.id;
      visibleInput.value = item.dataset.resultLabel || '';
      selected = true;
      onSelect(item.dataset);
    });

    visibleInput.addEventListener('blur', () => {
      setTimeout(() => { if (resultsBox) resultsBox.innerHTML = ''; }, 150);
    });
  }

  // ---------- product rows ----------
  function wireProductRow(row) {
    const visibleInput = row.querySelector('[data-product-combo-input]');
    const hiddenIdInput = row.querySelector('[data-product-combo-id]');
    const resultsBox = row.querySelector('[data-product-combo-results]');
    const priceInput = row.querySelector('[data-price]');
    const taxInput = row.querySelector('[data-tax]');
    const thumbEl = row.querySelector('[data-product-thumb]');

    [priceInput, taxInput, row.querySelector('[data-qty]'), row.querySelector('[data-discount]')].forEach((input) => {
      input?.addEventListener('input', recalcAll);
    });

    if (!visibleInput) return;

    wireCombo({
      visibleInput, hiddenIdInput, resultsBox, searchUrl: productSearchUrl,
      renderResult: (p) => `
        <div class="search-result" data-id="${p.id}" data-result-label="${p.name}${p.sku ? ' · ' + p.sku : ''}" data-result-price="${p.price}" data-result-tax="${p.tax}" data-result-thumb="${p.thumb || ''}">
          ${p.thumb ? `<img src="${p.thumb}" alt="">` : ''}
          <span>${p.name}${p.sku ? ' · ' + p.sku : ''} — AED ${parseFloat(p.price).toFixed(2)}</span>
        </div>`,
      onSelect: (data) => {
        if (priceInput) priceInput.value = parseFloat(data.resultPrice || 0).toFixed(2);
        if (taxInput) taxInput.value = parseFloat(data.resultTax || 0).toFixed(2);
        if (thumbEl) { thumbEl.src = data.resultThumb || ''; thumbEl.style.display = data.resultThumb ? '' : 'none'; }
        recalcAll();
        syncDeliveryLine();
      },
    });
  }

  if (itemsTable) {
    itemsTable.querySelectorAll('tbody tr[data-product-row]').forEach(wireProductRow);
    recalcAll();
  }

  function wireRemove(row) {
    row.querySelector('[data-remove-row]')?.addEventListener('click', () => { row.remove(); recalcAll(); });
  }
  itemsTable?.querySelectorAll('tbody tr[data-product-row]').forEach(wireRemove);

  // Delivery / Pickup: whenever fulfillment is "Delivery", the order must
  // carry exactly one "Delivery" line (price left for the user to set,
  // tax-free per the explicit requirement), and that line must always end
  // up as the LAST row — however many products get added afterward.
  // Declared as real function declarations (hoisted), not const arrow
  // functions, so they're callable from wireProductRow's onSelect further
  // up in this file regardless of textual order.
  const fulfillmentSelect = document.querySelector('[data-fulfillment-type]');

  function moveDeliveryLineToEnd() {
    const existing = itemsTable?.querySelector('tbody tr[data-auto-delivery-line]');
    if (existing) itemsTable.querySelector('tbody').appendChild(existing);
  }

  function syncDeliveryLine() {
    if (!fulfillmentSelect || !itemsTable || !manualRowTemplate) return;
    const existing = itemsTable.querySelector('tbody tr[data-auto-delivery-line]');
    if (fulfillmentSelect.value === 'delivery') {
      if (existing) { moveDeliveryLineToEnd(); return; }
      const html = manualRowTemplate.innerHTML.replaceAll('__INDEX__', rowIndex++);
      const tmp = document.createElement('tbody');
      tmp.innerHTML = html;
      const newRow = tmp.firstElementChild;
      newRow.setAttribute('data-auto-delivery-line', '1');
      const descInput = newRow.querySelector('input[name*="[description]"]');
      if (descInput) descInput.value = 'Delivery';
      const taxInput = newRow.querySelector('[data-tax]');
      if (taxInput) taxInput.value = '0';
      itemsTable.querySelector('tbody').appendChild(newRow);
      wireProductRow(newRow);
      wireRemove(newRow);
      recalcAll();
    } else if (existing) {
      existing.remove();
      recalcAll();
    }
  }

  document.querySelector('[data-add-row]')?.addEventListener('click', () => {
    if (!rowTemplate) return;
    const html = rowTemplate.innerHTML.replaceAll('__INDEX__', rowIndex++);
    const tmp = document.createElement('tbody');
    tmp.innerHTML = html;
    const newRow = tmp.firstElementChild;
    itemsTable.querySelector('tbody').appendChild(newRow);
    wireProductRow(newRow);
    wireRemove(newRow);
    moveDeliveryLineToEnd();
  });

  document.querySelector('[data-add-manual-row]')?.addEventListener('click', () => {
    if (!manualRowTemplate) return;
    const html = manualRowTemplate.innerHTML.replaceAll('__INDEX__', rowIndex++);
    const tmp = document.createElement('tbody');
    tmp.innerHTML = html;
    const newRow = tmp.firstElementChild;
    itemsTable.querySelector('tbody').appendChild(newRow);
    wireProductRow(newRow);
    wireRemove(newRow);
    moveDeliveryLineToEnd();
  });

  if (fulfillmentSelect) {
    fulfillmentSelect.addEventListener('change', syncDeliveryLine);
    // "Delivery" is the default value on a brand-new order form, so it's
    // already selected without the user ever triggering a change event —
    // meaning the line would otherwise never appear until they manually
    // switched away and back. Only do this for a genuinely NEW order
    // (never for editing an existing one, where a surprise new line
    // appearing on page load would be unexpected and wrong).
    if (form?.dataset.isNewOrder === '1') syncDeliveryLine();
  }

  // ---------- customer combo ----------
  const customerVisible = document.querySelector('[data-customer-combo-input]');
  const customerHiddenId = document.querySelector('[data-customer-combo-id]');
  const customerResults = document.querySelector('[data-customer-combo-results]');

  wireCombo({
    visibleInput: customerVisible, hiddenIdInput: customerHiddenId, resultsBox: customerResults,
    searchUrl: customerSearchUrl,
    renderResult: (c) => `
      <div class="search-result" data-id="${c.id}" data-result-label="${c.name}${c.phone ? ' · ' + c.phone : ''}" data-result-phone="${c.phone || ''}" data-result-address="${c.address || ''}" data-result-location="${c.location || ''}" data-result-area="${c.area || ''}">
        <span>${c.name}${c.phone ? ' — ' + c.phone : ''}${c.location ? ' — ' + c.location : ''}</span>
      </div>`,
    onSelect: (data) => {
      const phoneInput = document.querySelector('[data-customer-phone]');
      const addressInput = document.querySelector('[data-customer-address]');
      const locationSelect = document.querySelector('[data-customer-location]');
      if (phoneInput && data.resultPhone) phoneInput.value = data.resultPhone;
      if (addressInput && data.resultAddress) addressInput.value = data.resultAddress;
      if (locationSelect && data.resultLocation) {
        [...locationSelect.options].forEach((o) => { o.selected = o.value === data.resultLocation; });
      }
    },
    onClear: () => {},
  });

  function selectCustomerByData(customer) {
    if (customerHiddenId) customerHiddenId.value = customer.id;
    if (customerVisible) customerVisible.value = customer.phone ? `${customer.name} · ${customer.phone}` : customer.name;
    const phoneInput = document.querySelector('[data-customer-phone]');
    const addressInput = document.querySelector('[data-customer-address]');
    const locationSelect = document.querySelector('[data-customer-location]');
    if (phoneInput && customer.phone) phoneInput.value = customer.phone;
    if (addressInput && customer.address) addressInput.value = customer.address;
    if (locationSelect && customer.location) {
      [...locationSelect.options].forEach((o) => { o.selected = o.value === customer.location; });
    }
  }

  // ---------- quick-add dialogs ----------
  document.querySelectorAll('[data-open-dialog]').forEach((btn) => {
    btn.addEventListener('click', () => document.getElementById(btn.dataset.openDialog)?.showModal());
  });
  // Close button + click-outside-to-close for these dialogs is handled
  // globally in bootstrap.js (loaded on every page, unlike this file).

  async function postJson(url, data) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: JSON.stringify(data),
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      const err = new Error(body.message || 'Request failed.');
      err.body = body;
      throw err;
    }
    return body;
  }

  async function postForm(url, formEl) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: new FormData(formEl),
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      const messages = body.errors ? Object.values(body.errors).flat().join(' ') : (body.message || 'Request failed.');
      const err = new Error(messages);
      err.body = body;
      throw err;
    }
    return body;
  }

  const quickCustomerForm = document.querySelector('[data-quick-customer-form]');
  quickCustomerForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl = quickCustomerForm.querySelector('[data-form-error]');
    errorEl.textContent = '';
    const payload = Object.fromEntries(new FormData(quickCustomerForm).entries());
    try {
      const customer = await postJson(form.dataset.quickCustomer, payload);
      selectCustomerByData(customer);
      quickCustomerForm.closest('dialog').close();
      quickCustomerForm.reset();
    } catch (err) {
      if (err.body?.duplicate && err.body.customer) {
        selectCustomerByData(err.body.customer);
        quickCustomerForm.closest('dialog').close();
        quickCustomerForm.reset();
        window.showToast?.(err.body.message, 'warning');
      } else {
        errorEl.textContent = err.message;
      }
    }
  });

  const quickProductForm = document.querySelector('[data-quick-product-form]');
  quickProductForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl = quickProductForm.querySelector('[data-form-error]');
    errorEl.textContent = '';
    try {
      const product = await postForm(form.dataset.quickProduct, quickProductForm);
      const dialog = quickProductForm.closest('dialog');
      const targetRowIndex = dialog.dataset.targetRow;
      const rows = itemsTable.querySelectorAll('tbody tr[data-product-row]');
      const targetRow = targetRowIndex !== undefined && targetRowIndex !== '' ? rows[targetRowIndex] : null;
      if (targetRow) {
        const hiddenId = targetRow.querySelector('[data-product-combo-id]');
        const visible = targetRow.querySelector('[data-product-combo-input]');
        const priceInput = targetRow.querySelector('[data-price]');
        const taxInput = targetRow.querySelector('[data-tax]');
        if (hiddenId) hiddenId.value = product.id;
        if (visible) visible.value = product.sku ? `${product.name} · ${product.sku}` : product.name;
        if (priceInput) priceInput.value = parseFloat(product.price).toFixed(2);
        if (taxInput) taxInput.value = parseFloat(product.tax).toFixed(2);
        recalcAll();
      }
      dialog.close();
      quickProductForm.reset();
      const preview = quickProductForm.querySelector('[data-product-image-preview]');
      if (preview) preview.innerHTML = '';
    } catch (err) {
      errorEl.textContent = err.message;
    }
  });

  document.body.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-open-product-dialog]');
    if (!trigger) return;
    const row = trigger.closest('tr[data-product-row]');
    const rows = [...itemsTable.querySelectorAll('tbody tr[data-product-row]')];
    const dialog = document.getElementById('quick-product');
    if (dialog) dialog.dataset.targetRow = String(rows.indexOf(row));
  });
});

// Order Capacity Limits — proactively checks how many deliveries are
// already booked for the chosen delivery date and warns before
// submission if it's full, offering the next available date as a
// one-click fix, rather than only failing reactively later when a
// delivery note gets scheduled.
document.addEventListener('DOMContentLoaded', () => {
  const dateInput = document.querySelector('[data-capacity-check]');
  const messageEl = document.querySelector('[data-capacity-message]');
  if (!dateInput || !messageEl) return;

  let debounce;
  const check = async () => {
    const date = dateInput.value;
    if (!date) { messageEl.textContent = ''; return; }
    try {
      const res = await fetch(`/orders/check-capacity?date=${encodeURIComponent(date)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return;
      const data = await res.json();
      if (data.full) {
        messageEl.innerHTML = `⚠ Fully booked (${data.count}/${data.limit}). <a href="#" data-use-suggested="${data.suggested_date}">Use ${data.suggested_date} instead</a>`;
        messageEl.className = 'kpi-bad';
      } else {
        messageEl.textContent = `${data.count}/${data.limit} booked for this date.`;
        messageEl.className = 'muted';
      }
    } catch (e) { /* non-fatal — capacity display is advisory, not a hard block */ }
  };

  dateInput.addEventListener('change', () => { clearTimeout(debounce); debounce = setTimeout(check, 150); });
  messageEl.addEventListener('click', (e) => {
    const link = e.target.closest('[data-use-suggested]');
    if (!link) return;
    e.preventDefault();
    dateInput.value = link.dataset.useSuggested;
    check();
  });
  if (dateInput.value) check();
});
