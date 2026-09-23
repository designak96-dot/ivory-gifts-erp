{{-- Shown when a list was opened from a dashboard box, so it's clear the list is filtered. --}}
<div class="alert" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-color:rgba(34,211,238,.35)">
<span><b>Filtered:</b> {{ $label }}</span>
<a class="btn small" href="{{ $clearUrl }}">Show all ✕</a>
</div>
