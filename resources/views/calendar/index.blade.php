@extends('layouts.app')
@section('title','Calendar')
@section('subtitle','Deliveries, deadlines, tasks and expected receipts')
@section('content')
<div class="toolbar">
<div class="filters">
<a class="badge {{ $view==='month'?'blue':'' }}" href="{{ route('calendar.index',['view'=>'month','date'=>$anchor->toDateString()]) }}">Month</a>
<a class="badge {{ $view==='week'?'blue':'' }}" href="{{ route('calendar.index',['view'=>'week','date'=>$anchor->toDateString()]) }}">Week</a>
<a class="badge {{ $view==='day'?'blue':'' }}" href="{{ route('calendar.index',['view'=>'day','date'=>$anchor->toDateString()]) }}">Day</a>
</div>
<div style="display:flex;gap:8px;align-items:center">
<a class="btn small" href="{{ route('calendar.index',['view'=>$view,'date'=>$prevDate->toDateString()]) }}">← Prev</a>
<b>{{ $view==='month' ? $anchor->format('F Y') : ($view==='week' ? $start->format('d M').' – '.$end->format('d M Y') : $anchor->format('d F Y')) }}</b>
<a class="btn small" href="{{ route('calendar.index',['view'=>$view,'date'=>$nextDate->toDateString()]) }}">Next →</a>
<a class="btn small" href="{{ route('calendar.index',['view'=>$view]) }}">Today</a>
</div>
</div>

@if($view==='month')
<div class="card">
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:12px;overflow:hidden">
@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<div style="background:var(--badge-base);padding:8px;text-align:center;font-size:11px;font-weight:800;color:var(--muted)">{{ $d }}</div>@endforeach
@php($cursor=$start->copy())
@while($cursor->lte($end))
<div style="background:var(--surface);min-height:100px;padding:6px;{{ $cursor->isSameMonth($anchor)?'':'opacity:.4' }}">
<div style="font-size:11px;font-weight:800;{{ $cursor->isToday()?'color:var(--brown)':'' }}">{{ $cursor->day }}</div>
@foreach($events->get($cursor->toDateString(),collect())->take(4) as $e)
<a href="{{ $e['url'] }}" class="badge {{ $e['color'] }}" style="display:block;margin-top:3px;font-size:9.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $e['type'] }}: {{ $e['label'] }}</a>
@endforeach
@if($events->get($cursor->toDateString(),collect())->count()>4)<div class="muted" style="font-size:9.5px;margin-top:2px">+{{ $events->get($cursor->toDateString())->count()-4 }} more</div>@endif
</div>
@php($cursor->addDay())
@endwhile
</div>
</div>
@else
<div class="card">
@php($cursor=$start->copy())
@while($cursor->lte($end))
<div style="padding:12px 0;border-bottom:1px solid var(--line)">
<b>{{ $cursor->format('l, d F Y') }}</b>@if($cursor->isToday())<span class="badge blue" style="margin-left:6px">Today</span>@endif
<div style="margin-top:8px;display:flex;flex-direction:column;gap:6px">
@forelse($events->get($cursor->toDateString(),collect()) as $e)
<a href="{{ $e['url'] }}" class="health" style="text-decoration:none"><span class="dot {{ $e['color']==='red'?'error':($e['color']==='amber'?'warning':'ok') }}"></span><div><b>{{ $e['type'] }}</b><div class="muted">{{ $e['label'] }}</div></div></a>
@empty
<p class="muted" style="margin:0">No events.</p>
@endforelse
</div>
</div>
@php($cursor->addDay())
@endwhile
</div>
@endif
@endsection
