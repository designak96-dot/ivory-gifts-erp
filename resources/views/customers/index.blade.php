@extends('layouts.app')
@section('title','Customers')
@section('subtitle','Customer records, balances and WhatsApp contacts')
@section('content')
@include('partials._saved-filters', ['page' => 'customers'])
<div class="toolbar"><form><input name="q" value="{{ request('q') }}" placeholder="Search name, phone or code">@if(request('tag'))<input type="hidden" name="tag" value="{{ request('tag') }}">@endif<button class="btn">Search</button></form>@if(auth()->user()->hasPermission('customers.manage'))<a class="btn primary" href="{{ route('customers.create') }}">Add customer</a>@endif</div>
@if(request()->query())<form method="post" action="{{ route('saved-filters.store') }}" style="margin-bottom:15px">@csrf<input type="hidden" name="page" value="customers">@foreach(request()->query() as $k=>$v)<input type="hidden" name="params[{{ $k }}]" value="{{ $v }}">@endforeach<input name="name" placeholder="Save this filter as..." style="width:220px;display:inline-block"><button class="btn small">Save filter</button></form>@endif
<div class="filters" style="margin-bottom:15px">
<a class="badge {{ !request('tag')?'blue':'' }}" href="{{ route('customers.index',array_filter(['q'=>request('q')])) }}">All</a>
@foreach($tags as $t)<a class="badge {{ (string)request('tag')===(string)$t->id?'blue':'' }}" href="{{ route('customers.index',array_filter(['q'=>request('q'),'tag'=>$t->id])) }}">{{ $t->name }}</a>@endforeach
</div>
<div class="table-wrap mobile-cards"><table><thead><tr><th>Code</th><th>Customer</th><th>Phone / WhatsApp</th><th>Location</th><th>Terms</th><th>Tags</th><th>Status</th></tr></thead><tbody>@forelse($customers as $c)<tr><td data-label="Code"><a href="{{ route('customers.show',$c) }}"><b>{{ $c->customer_code }}</b></a></td><td data-label="Customer"><b>{{ $c->name }}</b><div class="muted">{{ $c->company_name?:$c->email }}</div></td><td data-label="Phone">{{ $c->phone }} @if($c->whatsapp_url)<a class="btn small success" target="_blank" rel="noopener" href="{{ $c->whatsapp_url }}">WhatsApp</a>@endif</td><td data-label="Location">{{ collect([$c->area,$c->emirate])->filter()->join(', ')?:'—' }}</td><td data-label="Terms">{{ $c->payment_terms_days }} days</td><td data-label="Tags">@foreach($c->tags as $t)<span class="badge {{ $t->color }}">{{ $t->name }}</span>@endforeach</td><td data-label="Status"><span class="badge {{ $c->status==='active'?'green':'red' }}">{{ $c->status }}</span></td></tr>@empty<tr><td colspan="7" class="empty">No customers found.</td></tr>@endforelse</tbody></table></div>{{ $customers->links() }}
@endsection
