{{--
  Shared product field set — used by both the main Products → Add/Edit
  Product page and the Sales Order "+ Add product" popup. A future field
  change (new column, different validation) only needs to happen here once,
  per the explicit instruction not to maintain two different product forms.

  Expects: $product (Product, possibly new/unsaved), $categories, $taxRates,
  and optionally $fieldPrefix (string) to namespace input names when this
  partial is embedded inside another form on the same page (the order
  form already has its own fields — without a prefix, "name_en" here would
  collide with nothing directly, but keeping every popup-scoped input under
  a prefix avoids any future collision and makes the JS payload building
  trivial: read everything under that prefix).
--}}
@php($prefix = $fieldPrefix ?? '')
@php($name = fn($field) => $prefix ? "{$prefix}[{$field}]" : $field)
<label>Product name<input name="{{ $name('name_en') }}" value="{{ old($name('name_en'),$product->name_en) }}" required></label>
<label>Arabic name<input dir="rtl" name="{{ $name('name_ar') }}" value="{{ old($name('name_ar'),$product->name_ar) }}"></label>
<label>SKU / Product Code<input name="{{ $name('sku') }}" value="{{ old($name('sku'),$product->sku) }}" placeholder="Leave blank to auto-generate" maxlength="40"></label>
<label>Barcode (optional)<input name="{{ $name('barcode') }}" value="{{ old($name('barcode'),$product->barcode) }}"></label>
<label>Category<select name="{{ $name('category_id') }}"><option value="">Uncategorised</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old($name('category_id'),$product->category_id)==$c->id)>{{ $c->name_en }}</option>@endforeach</select></label>
<label>Unit<input name="{{ $name('unit') }}" value="{{ old($name('unit'),$product->unit?:'piece') }}" required></label>
<label>Sale price (AED)<input type="number" step="0.01" min="0" name="{{ $name('sale_price') }}" value="{{ old($name('sale_price'),$product->sale_price??0) }}" required></label>
<label>Cost price (AED)<input type="number" step="0.01" min="0" name="{{ $name('cost_price') }}" value="{{ old($name('cost_price'),$product->cost_price??0) }}" required></label>
<label>Tax rate<select name="{{ $name('tax_rate_id') }}"><option value="">No tax</option>@foreach($taxRates as $tax)<option value="{{ $tax->id }}" @selected(old($name('tax_rate_id'),$product->tax_rate_id)==$tax->id)>{{ $tax->name }} ({{ $tax->rate }}%)</option>@endforeach</select></label>
<label>Reorder level<input type="number" step="0.001" min="0" name="{{ $name('reorder_level') }}" value="{{ old($name('reorder_level'),$product->reorder_level??0) }}"></label>
<label>Production time (days)<input type="number" min="0" name="{{ $name('production_time_days') }}" value="{{ old($name('production_time_days'),$product->production_time_days??0) }}"></label>
<label class="check"><input type="hidden" name="{{ $name('is_active') }}" value="0"><input type="checkbox" name="{{ $name('is_active') }}" value="1" @checked(old($name('is_active'),$product->exists?$product->is_active:true))> Active</label>
<label class="span-2">Product image (optional)<input type="file" name="{{ $name('image') }}" accept=".jpg,.jpeg,.png,.webp" data-product-image-input>
  <div data-product-image-preview style="margin-top:8px">
  @if($product->thumbnail_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->thumbnail_path) }}" alt="" style="max-height:80px;border-radius:8px">@endif
  </div>
</label>
<label class="span-2">Description<textarea name="{{ $name('description') }}">{{ old($name('description'),$product->description) }}</textarea></label>
