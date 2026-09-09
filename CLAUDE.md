

<!-- ivory-whatsapp-contract-v1 -->
## Ivory customer WhatsApp contract

Preserve this behavior in every unrelated update unless the user explicitly
requests a WhatsApp change. Do not replace fixed files with an older snapshot.

- Keep the include of partials._ready-whatsapp-js in resources/views/layouts/app.blade.php.
- Keep its single-install guard and capture handler for Ready and Delivered.
- Build BOTH messages in browser JavaScript using String.fromCodePoint:
  wave 0x1F44B, party 0x1F389, sparkles 0x2728, box 0x1F4E6,
  down 0x1F447, heart 0x1F90D. Do not use server message text as the final draft.
- Preserve Arabic first, English second, current wording, blank lines,
  WhatsApp asterisk bold, and exactly one secure customer share link.
- Ready status: طلبك جاهز! / Your order is ready!
- Delivered status: تم تسليم طلبك بنجاح! / Your order has been delivered!
- Keep one existing customer WhatsApp button. Open web.whatsapp.com/send
  directly, once-encode the final message with encodeURIComponent, and reuse
  ivory_whatsapp and the live window reference when the browser permits it.
- Never restore wa.me/api.whatsapp.com landing links or _blank for these
  customer buttons. Do not navigate the ERP tab or auto-send messages.
- Preserve phone normalization, secure-link endpoint, invoice/proof checks,
  popup-block retry, and Unicode Copy message fallback.
- Browser isolation/reloads can prevent tab reuse. Do not promise universal
  tab discovery or installed-app detection.

Before delivering any ERP update, from the ERP root run:
node whatsapp-emoji-payload/whatsapp-regression.mjs

If Node is unavailable on cPanel, run this check in the source checkout/CI
instead. Test the actual changed source, not a separate mock implementation.
Do not weaken these assertions to get a pass. Report a failed check rather
than presenting a regression as ready to install.

Include this contract, the regression test, the fixed partial, the layout
include and this CLAUDE.md guidance in the GitHub source used for future
updates. Server-only installation cannot protect an older GitHub checkout
from overwriting the fix.

