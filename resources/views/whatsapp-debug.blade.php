@extends('layouts.app')
@section('title','WhatsApp Encoding Debug')
@section('subtitle','Temporary verification tool — confirms Arabic + emoji survive every stage of the pipeline')
@section('content')
<div class="card">
<h2>WhatsApp Message Pipeline Debug</h2>
<p class="muted">Order: {{ $order->order_number }} — click the button below to run the real check → link flow and see each stage.</p>

<button type="button" class="btn primary" id="wa-debug-run" data-check-url="{{ route('whatsapp.check',$order) }}" data-link-url="{{ route('whatsapp.link',$order) }}">Run Debug Trace</button>

<div id="wa-debug-results" style="margin-top:20px;display:none">

<div class="card" style="margin-bottom:14px">
<h3>Stage 1 — Server response (message field, as received by fetch())</h3>
<pre id="wa-stage-1" style="white-space:pre-wrap;word-break:break-word;background:var(--surface-input);padding:14px;border-radius:10px;font-family:monospace;font-size:13px"></pre>
</div>

<div class="card" style="margin-bottom:14px">
<h3>Stage 2 — DOM: message written into a hidden element and read back</h3>
<pre id="wa-stage-2" style="white-space:pre-wrap;word-break:break-word;background:var(--surface-input);padding:14px;border-radius:10px;font-family:monospace;font-size:13px"></pre>
<div id="wa-dom-holder" style="display:none"></div>
</div>

<div class="card" style="margin-bottom:14px">
<h3>Stage 3 — After encodeURIComponent()</h3>
<pre id="wa-stage-3" style="white-space:pre-wrap;word-break:break-all;background:var(--surface-input);padding:14px;border-radius:10px;font-family:monospace;font-size:11px"></pre>
</div>

<div class="card" style="margin-bottom:14px">
<h3>Stage 4 — Final: decodeURIComponent() of the exact string that would be sent to WhatsApp</h3>
<pre id="wa-stage-4" style="white-space:pre-wrap;word-break:break-word;background:var(--surface-input);padding:14px;border-radius:10px;font-family:monospace;font-size:13px"></pre>
</div>

<div class="card" id="wa-verdict"></div>

<a class="btn success" id="wa-open-real" target="_blank" style="margin-top:14px">Open the real WhatsApp link now</a>
</div>
</div>

<script>
document.getElementById('wa-debug-run').addEventListener('click', async function(){
  const btn = this;
  btn.disabled = true;
  try {
    const checkRes = await fetch(btn.dataset.checkUrl, { headers: { Accept: 'application/json' } });
    const check = await checkRes.json();
    if (!check.has_invoice) { alert('This order has no invoice yet — generate one first, then re-run.'); btn.disabled = false; return; }

    const linkRes = await fetch(btn.dataset.linkUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' } });
    const data = await linkRes.json();

    // Stage 1: exactly what fetch().json() gave us.
    document.getElementById('wa-stage-1').textContent = data.message;

    // Stage 2: write it into the DOM (as a real attribute AND as text content, both ways content could live in the page), then read it back.
    const holder = document.getElementById('wa-dom-holder');
    holder.setAttribute('data-message', data.message);
    holder.textContent = data.message;
    const domMessage = holder.getAttribute('data-message');
    document.getElementById('wa-stage-2').textContent = domMessage;

    // Stage 3: after encodeURIComponent.
    const encoded = encodeURIComponent(domMessage);
    document.getElementById('wa-stage-3').textContent = encoded;

    // Stage 4: decode it back — this is exactly the string WhatsApp's own URL parser would see.
    const decoded = decodeURIComponent(encoded);
    document.getElementById('wa-stage-4').textContent = decoded;

    // Verdict
    const allMatch = data.message === domMessage && domMessage === decoded;
    const requiredEmojis = ['\u{1F44B}', '\u{1F389}', '\u{2728}', '\u{1F4E6}', '\u{1F447}', '\u{1F90D}'];
    const emojiLabels = ['👋 wave', '🎉 party', '✨ sparkles', '📦 package', '👇 pointing down', '🤍 white heart'];
    const emojiResults = requiredEmojis.map((e, i) => ({ label: emojiLabels[i], present: decoded.includes(e) }));
    const hasReplacementChar = decoded.includes('\uFFFD');
    const allEmojisPresent = emojiResults.every(r => r.present);

    const verdict = document.getElementById('wa-verdict');
    let html = '<h3>Verdict</h3>';
    html += '<p><b>All 4 stages identical:</b> <span style="color:' + (allMatch ? '#5eead4' : '#fca5b5') + '">' + (allMatch ? 'YES — byte-for-byte match' : 'NO — mismatch detected') + '</span></p>';
    html += '<p><b>Replacement character (\uFFFD) present:</b> <span style="color:' + (hasReplacementChar ? '#fca5b5' : '#5eead4') + '">' + (hasReplacementChar ? 'YES — corruption detected' : 'NO — clean') + '</span></p>';
    html += '<ul>' + emojiResults.map(r => '<li style="color:' + (r.present ? '#5eead4' : '#fca5b5') + '">' + r.label + ': ' + (r.present ? 'OK' : 'MISSING/BROKEN') + '</li>').join('') + '</ul>';
    verdict.innerHTML = html;

    document.getElementById('wa-debug-results').style.display = 'block';
    document.getElementById('wa-open-real').href = 'https://wa.me/' + data.phone + '?text=' + encoded;
  } catch (e) {
    alert('Debug trace failed: ' + e.message);
  } finally {
    btn.disabled = false;
  }
});
</script>
@endsection
