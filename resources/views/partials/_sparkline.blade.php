{{--
  Small inline SVG trend line for a KPI card. Takes $values (an array of
  real numbers — this project's dashboard only ever passes genuine
  historical figures already computed by the controller, e.g. monthly
  revenue/expenses; never fabricated data). Renders nothing if there
  isn't enough real data to draw a meaningful line.
--}}
@if(isset($values) && count(array_filter($values, fn($v) => $v !== null)) >= 2)
@php
    $w = 88; $h = 30; $pad = 3;
    $min = min($values); $max = max($values);
    $range = ($max - $min) ?: 1;
    $n = count($values);
    $points = collect($values)->values()->map(function ($v, $i) use ($n, $w, $h, $pad, $min, $range) {
        $x = $n > 1 ? ($i / ($n - 1)) * ($w - 2 * $pad) + $pad : $w / 2;
        $y = $h - $pad - (($v - $min) / $range) * ($h - 2 * $pad);
        return round($x, 1).','.round($y, 1);
    })->implode(' ');
@endphp
<svg class="stat-trend" viewBox="0 0 {{ $w }} {{ $h }}" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <polyline points="{{ $points }}" stroke="{{ $color ?? '#22d3ee' }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
@endif
