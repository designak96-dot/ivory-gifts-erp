<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $orderNumber }} — Ivory Gifts</title>
<meta name="robots" content="noindex, nofollow">
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#0d1120;color:#e7ebf7;margin:0;padding:20px;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{max-width:480px;width:100%;background:#151b2e;border:1px solid rgba(148,163,204,.15);border-radius:16px;padding:30px}
.logo{font-size:22px;font-weight:800;color:#e11d48;margin-bottom:4px}
.logo span{color:#e7ebf7}
h1{font-size:18px;margin:20px 0 4px}
.row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(148,163,204,.1)}
.row small{color:#8890ab}
.row b{text-align:right}
.docs{margin-top:20px;display:flex;flex-direction:column;gap:10px}
.doc-row{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;background:rgba(255,255,255,.03);padding:12px 14px;border-radius:10px}
.doc-row-actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-block;padding:7px 14px;border-radius:8px;background:#22d3ee;color:#0d1120;text-decoration:none;font-weight:700;font-size:13px;border:0;cursor:pointer;font-family:inherit}
@media(max-width:420px){.doc-row{flex-direction:column;align-items:flex-start}.doc-row-actions{width:100%}.doc-row-actions .btn{flex:1;text-align:center}}
.status{display:inline-block;padding:4px 10px;border-radius:99px;font-size:12px;font-weight:700}
.status-Pending{background:rgba(232,162,61,.2);color:#fbbf6d}
.status-Ready{background:rgba(59,130,246,.2);color:#93c5fd}
.status-Delivered{background:rgba(45,212,168,.2);color:#5eead4}
.status-Canceled{background:rgba(240,85,111,.2);color:#fca5b5}
.share-modal-overlay{position:fixed;inset:0;background:rgba(2,4,10,.8);z-index:200;display:none;align-items:center;justify-content:center;padding:16px}
.share-modal-overlay.open{display:flex}
.share-modal-box{background:#fff;width:min(820px,100%);height:min(88vh,900px);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 30px 80px rgba(0,0,0,.5)}
.share-modal-head{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#151b2e}
.share-modal-head button{background:none;border:0;color:#e7ebf7;font-size:20px;cursor:pointer;line-height:1}
.share-modal-box iframe{flex:1;border:0;width:100%}
</style>
</head>
<body>
<div class="card">
<div class="logo">ivo<span>&#9733;</span>ry <span style="font-weight:400">Gifts</span></div>
<h1>Order {{ $orderNumber }}</h1>
<span class="status status-{{ $status }}">{{ $status }}</span>
<div style="margin-top:18px">
<div class="row"><small>Customer</small><b>{{ $customerName }}</b></div>
@if($invoiceNumber)<div class="row"><small>Invoice Number</small><b>{{ $invoiceNumber }}</b></div>@endif
<div class="row"><small>Total</small><b>AED {{ number_format($total,2) }}</b></div>
<div class="row"><small>Paid</small><b>AED {{ number_format($paid,2) }}</b></div>
<div class="row"><small>Remaining</small><b>AED {{ number_format($remaining,2) }}</b></div>
</div>
<div class="docs">
@if($hasInvoice)
<div class="doc-row"><span>Invoice</span><span class="doc-row-actions"><button type="button" class="btn" data-share-view="{{ route('share.invoice',$token) }}">View</button><a class="btn" href="{{ route('share.invoice',$token) }}?download=1" target="_blank">Download</a></span></div>
@else
<div class="doc-row"><span style="color:#8890ab">Invoice not yet available</span></div>
@endif
@if($hasProof)
<div class="doc-row"><span>Confirmed Order</span><span class="doc-row-actions"><button type="button" class="btn" data-share-view="{{ route('share.proof',$token) }}?inline=1">View</button><a class="btn" href="{{ route('share.proof',$token) }}">Download</a></span></div>
@endif
</div>
</div>

<div class="share-modal-overlay" data-share-modal>
<div class="share-modal-box">
<div class="share-modal-head"><span>Ivory Gifts</span><button type="button" data-share-modal-close aria-label="Close">×</button></div>
<iframe data-share-modal-frame src="about:blank"></iframe>
</div>
</div>

<script>
(function(){
  var overlay = document.querySelector('[data-share-modal]');
  var frame = document.querySelector('[data-share-modal-frame]');
  document.querySelectorAll('[data-share-view]').forEach(function(btn){
    btn.addEventListener('click', function(){
      frame.src = btn.dataset.shareView;
      overlay.classList.add('open');
    });
  });
  function closeModal(){ overlay.classList.remove('open'); frame.src = 'about:blank'; }
  document.querySelector('[data-share-modal-close]').addEventListener('click', closeModal);
  overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
})();
</script>
</body>
</html>
