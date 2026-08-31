<script>
(() => {
    const wave = String.fromCodePoint(0x1F44B);
    const party = String.fromCodePoint(0x1F389);
    const sparkles = String.fromCodePoint(0x2728);
    const box = String.fromCodePoint(0x1F4E6);
    const down = String.fromCodePoint(0x1F447);
    const heart = String.fromCodePoint(0x1F90D);

    const buildReadyMessage = (data) => [
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

    const buildModal = (title, message, buttons) => {
        const overlay = document.createElement('div');
        overlay.className = 'wa-modal-overlay';
        const modal = document.createElement('div');
        modal.className = 'wa-modal-box';
        modal.innerHTML = `<h3>${title}</h3><p>${message}</p><div class="wa-modal-actions"></div>`;
        const actions = modal.querySelector('.wa-modal-actions');

        buttons.forEach(({ label, primary, onClick }) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn' + (primary ? ' primary' : '');
            button.textContent = label;
            button.addEventListener('click', () => {
                overlay.remove();
                onClick?.();
            });
            actions.appendChild(button);
        });

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) overlay.remove();
        });
    };

    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-whatsapp-share][data-whatsapp-status="ready"]');
        if (!btn) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        if (btn.disabled) return;

        btn.disabled = true;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        try {
            const checkRes = await fetch(btn.dataset.checkUrl, { headers: { Accept: 'application/json' } });
            const check = await checkRes.json();

            if (!check.has_invoice) {
                buildModal('Invoice required', 'Invoice has not been generated for this order.', [
                    { label: 'Cancel' },
                    {
                        label: 'Generate Invoice',
                        primary: true,
                        onClick: () => {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = check.generate_invoice_url;
                            form.innerHTML = `<input type="hidden" name="_token" value="${csrfToken}">`;
                            document.body.appendChild(form);
                            form.submit();
                        },
                    },
                ]);
                return;
            }

            const openWhatsApp = async () => {
                const linkRes = await fetch(btn.dataset.linkUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                });

                if (!linkRes.ok) {
                    alert('Could not prepare the WhatsApp message — please try again.');
                    return;
                }

                const data = await linkRes.json();
                const finalMessage = buildReadyMessage(data);
                const whatsappUrl = 'https://wa.me/' + data.phone + '?text=' + encodeURIComponent(finalMessage);
                window.open(whatsappUrl, '_blank');
            };

            if (!check.has_proof) {
                buildModal('Confirmed Order proof', 'Confirmed Order proof has not been uploaded.', [
                    { label: 'Continue Without Proof', primary: true, onClick: openWhatsApp },
                    { label: 'Upload Proof', onClick: () => document.querySelector('[data-proof-trigger]')?.click() },
                ]);
                return;
            }

            await openWhatsApp();
        } catch (error) {
            alert('Could not check order status — please try again.');
        } finally {
            btn.disabled = false;
        }
    }, true);
})();
</script>
