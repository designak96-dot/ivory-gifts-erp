{{--
  Shared full customer address block — used on Quotation, Invoice, and
  Delivery Note (screen + print/PDF, since print only hides .no-print
  elements, not this). One place so the address fields shown never drift
  out of sync between documents. Expects $customer.
  Empty fields are omitted entirely — no blank "Area:" label with nothing
  after it.
--}}
@php($effectiveDeliveryAddress = $deliveryAddressOverride ?? $customer->delivery_address)
<p>
  @if($customer->phone){{ $customer->phone }}<br>@endif
  @if($customer->emirate)Delivery Location: {{ $customer->emirate }}<br>@endif
  @if($customer->area)Area: {{ $customer->area }}<br>@endif
  @if($effectiveDeliveryAddress)Delivery Address: {{ $effectiveDeliveryAddress }}<br>@endif
  @if($customer->billing_address && $customer->billing_address !== $effectiveDeliveryAddress)Billing Address: {{ $customer->billing_address }}<br>@endif
  @if($customer->trn)TRN {{ $customer->trn }}@endif
</p>
