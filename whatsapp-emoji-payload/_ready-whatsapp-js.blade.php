<script>
(() => {
    if (window.ivoryReadyWhatsAppInstalled) return;
    window.ivoryReadyWhatsAppInstalled = true;
    const wave = String.fromCodePoint(0x1F44B);
    const party = String.fromCodePoint(0x1F389);
    const sparkles = String.fromCodePoint(0x2728);
    const box = String.fromCodePoint(0x1F4E6);
    const down = String.fromCodePoint(0x1F447);
    const heart = String.fromCodePoint(0x1F90D);

    const buildMessage = (data) => {
        const isDelivered = data.status === 'delivered';
        const arabicStatus = isDelivered ? `تم تسليم طلبك بنجاح! ${party}${sparkles}` : `طلبك جاهز! ${party}${sparkles}`;
        const englishStatus = isDelivered ? `Your order has been delivered! ${party}${sparkles}` : `Your order is ready! ${party}${sparkles}`;

        return [
            `مرحبا ${data.customer_name} ${wave}`,
            '',
            arabicStatus,
            '',
            `${box} *الطلب ${data.order_number}*`,
            '',
            `يمكنك مشاهدة الفاتورة وتفاصيل الطلب هنا ${down}`,
            '',
            `Hi ${data.customer_name} ${wave}`,
            '',
            englishStatus,
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
    };

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

    // Browser-built Unicode is the source of truth. Keep a manual copy
    // fallback available if a WhatsApp client does not import the draft.
    const copyMessage = async (message) => {
        try {
            await navigator.clipboard.writeText(message);
            return true;
        } catch {
            const textarea = document.createElement('textarea');
            textarea.value = message;
            textarea.setAttribute('readonly', '');
            textarea.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const copied = document.execCommand('copy');
            textarea.remove();
            return copied;
        }
    };

    let whatsappWindow = null;
    const openReadyWindow = (url) => {
        if (whatsappWindow && !whatsappWindow.closed) {
            try {
                whatsappWindow.location.href = url;
                whatsappWindow.focus();
                return true;
            } catch {
                whatsappWindow = null;
            }
        }
        whatsappWindow = window.open(url, 'ivory_whatsapp');
        whatsappWindow?.focus();
        return Boolean(whatsappWindow);
    };

    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-whatsapp-share][data-whatsapp-status="ready"], [data-whatsapp-share][data-whatsapp-status="delivered"], [data-ready-whatsapp-share][data-whatsapp-status="ready"], [data-ready-whatsapp-share][data-whatsapp-status="delivered"]');
        if (!btn) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        if (btn.disabled) return;

        btn.disabled = true;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        try {
            const checkRes = await fetch(btn.dataset.checkUrl, { headers: { Accept: 'application/json' } });
            if (!checkRes.ok) throw new Error('Order check failed');
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
                if (!['ready', 'delivered'].includes(data.status)) {
                    alert('The order status changed. Refresh this page before sharing.');
                    return;
                }
                // Both customer messages must be built in the browser so an
                // older PHP/DB message cannot strip or corrupt their emojis.
                const message = buildMessage(data);
                if (typeof message !== 'string' || !message) {
                    throw new Error('WhatsApp message is missing');
                }
                const whatsappUrl = 'https://web.whatsapp.com/send?phone='
                    + encodeURIComponent(data.phone) + '&text=' + encodeURIComponent(message);
                const opened = openReadyWindow(whatsappUrl);

                buildModal(
                    opened ? 'WhatsApp message prepared' : 'Allow WhatsApp to open',
                    opened
                        ? 'WhatsApp Web was requested with your message. If the draft does not appear correctly, copy the original message here and paste it into the chat.'
                        : 'Your browser blocked the WhatsApp window. Select Open WhatsApp to continue.',
                    [
                        { label: opened ? 'Done' : 'Open WhatsApp', primary: true,
                            onClick: opened ? undefined : () => openReadyWindow(whatsappUrl) },
                        { label: 'Copy message', onClick: async () => {
                            const copied = await copyMessage(message);
                            alert(copied ? 'Message copied. Paste it into WhatsApp.' : 'Clipboard access failed. Please allow clipboard access and retry.');
                        } },
                    ]
                );
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
