@extends('layouts.app')
@section('title',$isDriverOnly?'My Deliveries':'Delivery Dashboard')
@section('subtitle',now()->timezone('Asia/Dubai')->format('l, d F Y').' · Live working schedule')
@section('content')
<div class="delivery-month-bar"><form method="get"><label>Selected month<input type="month" name="month" value="{{ $calendarMonth->format('Y-m') }}" onchange="this.form.submit()"></label></form>@unless($isDriverOnly)<div class="header-actions"><a class="btn" href="{{ route('deliveries.report') }}">Delivery reports</a>@if(auth()->user()->hasPermission('orders.manage'))<a class="btn primary" href="{{ route('orders.create') }}">+ New sales order</a>@endif</div>@endunless</div>
@unless($isDriverOnly)
<details class="delivery-calendar-panel"><summary>Delivery calendar — {{ $calendarMonth->format('F Y') }}</summary>
<div class="delivery-calendar-grid">
@foreach($calendarDays as $day)
<a class="delivery-calendar-day @if($day['count']>0) has-deliveries @endif @if($day['date']===now()->timezone('Asia/Dubai')->toDateString()) is-today @endif"
   href="{{ route('deliveries.index',['month'=>$calendarMonth->format('Y-m'),'date'=>$day['date']]) }}">
  <span class="day-number">{{ \Carbon\Carbon::parse($day['date'])->format('j') }}</span>
  @if($day['count']>0)<span class="day-count">{{ $day['count'] }}</span>@endif
</a>
@endforeach
</div>
</details>
@endunless
<div data-delivery-live data-version="{{ $version }}" data-live-url="{{ route('deliveries.live',request()->query()) }}">@include('deliveries._live')</div>
@endsection
@push('scripts')<script src="{{ asset('build/assets/delivery.js') }}?v={{ @filemtime(public_path('build/assets/delivery.js')) ?: time() }}" defer></script>@endpush
