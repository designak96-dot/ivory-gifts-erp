@extends('layouts.app') @section('title','Settings') @section('subtitle','Company, tax and numbering configuration')
@section('content')<div class="grid cols-2"><form method="post" action="{{ route('settings.update') }}" class="card">@csrf @method('patch')<h2>Company settings</h2><div class="stack" style="margin-top:15px"><label>Company name<input name="company_name" value="{{ $settings['company_name']??'Ivory Gifts' }}" required></label><label>Email<input type="email" name="company_email" value="{{ $settings['company_email']??'' }}"></label><label>Phone<input name="company_phone" value="{{ $settings['company_phone']??'' }}"></label><label>Address<textarea name="company_address">{{ $settings['company_address']??'' }}</textarea></label><label>TRN<input name="company_trn" value="{{ $settings['company_trn']??'' }}"></label><label>Currency<input name="currency" maxlength="3" value="{{ $settings['currency']??'AED' }}"></label><label>Timezone<input name="timezone" value="{{ $settings['timezone']??'Asia/Dubai' }}"></label><label>Maximum deliveries per day<input type="number" min="1" name="delivery_limit_per_day" value="{{ $settings['delivery_limit_per_day']??6 }}"></label><button class="btn primary">Save settings</button></div></form>

<form method="post" action="{{ route('settings.branding') }}" enctype="multipart/form-data" class="card" style="margin-top:18px">@csrf<h2>Company branding & documents</h2><div class="stack" style="margin-top:15px">
<label>Legal name<input name="company_legal_name" value="{{ $settings['company_legal_name']??'' }}"></label>
<label>Trade name<input name="company_trade_name" value="{{ $settings['company_trade_name']??'' }}"></label>
<label>Website<input type="url" name="company_website" value="{{ $settings['company_website']??'' }}"></label>
<label>Bank / payment details<textarea name="company_bank_details">{{ $settings['company_bank_details']??'' }}</textarea></label>
<label>Default quotation terms<textarea name="quotation_terms">{{ $settings['quotation_terms']??'' }}</textarea></label>
<label>Default invoice terms<textarea name="invoice_terms">{{ $settings['invoice_terms']??'' }}</textarea></label>
<label>Document footer<textarea name="document_footer">{{ $settings['document_footer']??'' }}</textarea></label>
<label class="check"><input type="checkbox" name="delivery_note_hide_prices" value="1" @checked(($settings['delivery_note_hide_prices']??'0')==='1')> Hide prices on delivery notes by default</label>

<div class="branding-asset">
<span>Company logo</span>
@if($companyLogoUrl ?? null)<img src="{{ $companyLogoUrl }}" alt="Current logo" style="max-height:60px">
<button type="submit" form="remove-logo-form" class="btn small danger">Remove logo</button>@endif
<input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg">
</div>

<div class="branding-asset">
<span>Authorized signature</span>
@if($settings['signature_path']??null)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings['signature_path']) }}" alt="Current signature" style="max-height:60px">
<button type="submit" form="remove-signature-form" class="btn small danger">Remove signature</button>@endif
<input type="file" name="signature" accept=".png,.jpg,.jpeg,.webp,.svg">
</div>

<button class="btn primary">Save branding</button>
</div></form>
<form id="remove-logo-form" method="post" action="{{ route('settings.branding.logo.remove') }}">@csrf @method('delete')</form>
<form id="remove-signature-form" method="post" action="{{ route('settings.branding.signature.remove') }}">@csrf @method('delete')</form>

<div><form method="post" action="{{ route('settings.tax') }}" class="card">@csrf<h2>Tax rates</h2><div class="form-grid" style="margin-top:15px"><label>Name<input name="name" required></label><label>Rate %<input type="number" step=".0001" min="0" max="100" name="rate" required></label><label class="check"><input type="checkbox" name="is_inclusive" value="1"> Inclusive</label><input type="hidden" name="is_active" value="1"></div><div class="actions"><button class="btn">Add tax rate</button></div><div class="table-wrap"><table><tbody>@foreach($taxRates as $t)<tr><td>{{ $t->name }}</td><td>{{ $t->rate }}%</td><td><span class="badge {{ $t->is_active?'green':'red' }}">{{ $t->is_active?'Active':'Inactive' }}</span></td></tr>@endforeach</tbody></table></div></form><div class="card" style="margin-top:18px"><h2>Numbering sequences</h2><div class="table-wrap" style="margin-top:15px"><table><thead><tr><th>Document</th><th>Prefix</th><th>Current</th><th>Reset</th></tr></thead><tbody>@foreach($sequences as $s)<tr><td>{{ str_replace('_',' ',$s->document_type) }}</td><td>{{ $s->prefix }}</td><td>{{ $s->current_value }}</td><td>{{ $s->reset_policy }}</td></tr>@endforeach</tbody></table></div></div>

@if(auth()->user()->hasRole('owner'))
<div class="card" style="margin-top:18px;border-color:var(--red)">
<h2 style="color:var(--red)">Danger Zone — Reset All Trial Data</h2>
<p class="muted" style="margin-top:8px">Permanently deletes every customer, sales order, quotation, invoice, payment, expense, the entire general ledger, audit log, and all other trial records. <b>Sales Products, categories, tax rates, and your account are kept.</b> This cannot be undone.</p>
<form method="post" action="{{ route('settings.reset-to-products-only') }}" style="margin-top:15px" onsubmit="return confirm('This will permanently delete all trial data except Sales Products. Are you absolutely sure?');">
@csrf
<label>Type <code>DELETE ALL DATA</code> to confirm<input name="confirmation" required placeholder="DELETE ALL DATA" autocomplete="off"></label>
<div class="actions"><button class="btn danger">Reset All Data — Keep Only Products</button></div>
</form>
</div>
@endif
</div></div>@endsection
