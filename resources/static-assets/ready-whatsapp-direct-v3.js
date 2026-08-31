document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  function buildModal(title, message, buttons) {
    const overlay = document.createElement('div');
    overlay.className = 'wa-modal-overlay';
    const box = document.createElement('div');
    box.className = 'wa-modal-box';
    box.innerHTML = `<h3>${title}</h3><p>${message}</p><div class="wa-modal-actions"></div>`;
    const actions = box.querySelector('.wa-modal-actions');
    buttons.forEach(({ label, primary, onClick }) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn' + (primary ? ' primary' : '');
      button.textContent = label;
      button.addEventListener('click', () => { overlay.remove(); onClick?.(); });
      actions.appendChild(button);
    });
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (event) => { if (event.target === overlay) overlay.remove(); });
  }

  document.querySelectorAll('[data-ready-whatsapp-share]').forEach((button) => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const checkResponse = await fetch(button.dataset.checkUrl, { headers: { Accept: 'application/json' } });
        const check = await checkResponse.json();

        if (!check.has_invoice) {
          buildModal('Invoice required', 'Invoice has not been generated for this order.', [
            { label: 'Cancel' },
            { label: 'Generate Invoice', primary: true, onClick: () => {
              const form = document.createElement('form');
              form.method = 'POST';
              form.action = check.generate_invoice_url;
              form.innerHTML = `<input type="hidden" name="_token" value="${csrfToken}">`;
              document.body.appendChild(form);
              form.submit();
            } },
          ]);
          return;
        }

        const openWhatsApp = async () => {
          const linkResponse = await fetch(button.dataset.linkUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
          });
          if (!linkResponse.ok) {
            alert('Could not prepare the WhatsApp message — please try again.');
            return;
          }

          const data = await linkResponse.json();
          const wave = String.fromCodePoint(0x1F44B);
          const party = String.fromCodePoint(0x1F389);
          const sparkles = String.fromCodePoint(0x2728);
          const box = String.fromCodePoint(0x1F4E6);
          const down = String.fromCodePoint(0x1F447);
          const heart = String.fromCodePoint(0x1F90D);

          const message = [
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

          const whatsappUrl = new URL('https://web.whatsapp.com/send');
          whatsappUrl.searchParams.set('phone', data.phone);
          whatsappUrl.searchParams.set('text', message);
          window.open(whatsappUrl.toString(), '_blank');
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
        button.disabled = false;
      }
    });
  });
});
