<script>
(() => {
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

    // Copy-to-clipboard + a phone-only WhatsApp URL (no `text=` parameter at
    // all) — confirmed, verified working technique. WhatsApp's own URL
    // importer for the `text=` parameter is what corrupts/strips emojis and
    // Arabic text for long, formatted messages; bypassing it entirely by
    // using the OS clipboard as the transport preserves every character
    // exactly. This costs one extra manual step (paste), but that trade-off
    // is what actually works, verified directly against real WhatsApp
    // behavior — unlike the URL-encoding approach this replaces, which
    // could not be made to reliably skip WhatsApp's own interstitial page
    // or preserve emojis through it.
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

    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-whatsapp-share], [data-ready-whatsapp-share]');
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
                const message = buildMessage(data);
                const phoneOnlyUrl = 'https://wa.me/' + encodeURIComponent(data.phone);

                const copied = await copyMessage(message);
                if (!copied) {
                    alert('Could not copy the WhatsApp message — please try again.');
                    return;
                }

                buildModal(
                    'Message copied',
                    'Open WhatsApp, then paste the message with Ctrl+V (or press and hold, then Paste, on mobile). If a draft is already there, select it first with Ctrl+A so the paste replaces it cleanly.',
                    [{ label: 'Open WhatsApp', primary: true, onClick: () => window.open(phoneOnlyUrl, '_blank', 'noopener') }]
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
