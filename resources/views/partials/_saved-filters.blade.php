{{-- Reusable saved-filter chip row. Include with @include('partials._saved-filters', ['page' => 'orders']) --}}
@php($savedFilters = \App\Models\SavedFilter::where('page', $page)->latest()->get())
@if($savedFilters->count())
<div class="filters" style="margin-bottom:12px">
@foreach($savedFilters as $f)
<a class="badge blue" href="{{ url()->current() }}?{{ http_build_query($f->params) }}">{{ $f->name }}</a>
@if($f->created_by===auth()->id())
<form method="post" action="{{ route('saved-filters.destroy',$f) }}" style="display:inline" data-confirm="Remove the saved filter '{{ $f->name }}'?">@csrf @method('delete')<button type="submit" class="link" style="font-size:10px">×</button></form>
@endif
@endforeach
</div>
@endif
