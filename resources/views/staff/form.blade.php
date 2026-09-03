@extends('layouts.app')
@section('title',$staff->exists ? 'Edit '.$staff->name : 'Add Staff')
@section('content')
<div class="card">
<form method="post" action="{{ $staff->exists ? route('staff.update',$staff) : route('staff.store') }}">
@csrf
@if($staff->exists)@method('PUT')@endif
<div class="form-grid">
<label>Full Name<input name="name" value="{{ old('name',$staff->name) }}" required></label>
<label>Phone<input name="phone" value="{{ old('phone',$staff->phone) }}"></label>
<label>Email<input type="email" name="email" value="{{ old('email',$staff->email) }}"></label>
<label>Nationality<input name="nationality" value="{{ old('nationality',$staff->nationality) }}"></label>
<label>Job Title<input name="job_title" value="{{ old('job_title',$staff->job_title) }}"></label>
<label>Joining Date<input type="date" name="joining_date" value="{{ old('joining_date',$staff->joining_date?->toDateString()) }}"></label>
<label>Current Salary (AED)<input type="number" step="0.01" min="0" name="current_salary" value="{{ old('current_salary',$staff->current_salary) }}" required></label>
<label>Employment Status<select name="employment_status"><option value="active" @selected($staff->employment_status==='active')>Active</option><option value="on_leave" @selected($staff->employment_status==='on_leave')>On Leave</option><option value="resigned" @selected($staff->employment_status==='resigned')>Resigned</option><option value="terminated" @selected($staff->employment_status==='terminated')>Terminated</option></select></label>
@if($staff->exists)<label>Reason for salary change <span class="muted">(if changed)</span><input name="salary_change_reason" placeholder="e.g. Annual raise"></label>@endif

<label>Bank Name<input name="bank_name" value="{{ old('bank_name',$staff->bank_name) }}"></label>
<label>Bank Account Number<input name="bank_account_number" value="{{ old('bank_account_number',$staff->bank_account_number) }}"></label>
<label>IBAN<input name="bank_iban" value="{{ old('bank_iban',$staff->bank_iban) }}"></label>

<label>Emergency Contact Name<input name="emergency_contact_name" value="{{ old('emergency_contact_name',$staff->emergency_contact_name) }}"></label>
<label>Emergency Contact Phone<input name="emergency_contact_phone" value="{{ old('emergency_contact_phone',$staff->emergency_contact_phone) }}"></label>
<label>Relation<input name="emergency_contact_relation" value="{{ old('emergency_contact_relation',$staff->emergency_contact_relation) }}"></label>

<label>Passport Number<input name="passport_number" value="{{ old('passport_number',$staff->passport_number) }}"></label>
<label>Passport Expiry<input type="date" name="passport_expiry" value="{{ old('passport_expiry',$staff->passport_expiry?->toDateString()) }}"></label>
<label>Visa Number<input name="visa_number" value="{{ old('visa_number',$staff->visa_number) }}"></label>
<label>Visa Type<input name="visa_type" value="{{ old('visa_type',$staff->visa_type) }}"></label>
<label>Visa Expiry<input type="date" name="visa_expiry" value="{{ old('visa_expiry',$staff->visa_expiry?->toDateString()) }}"></label>
<label>Emirates ID Number<input name="emirates_id_number" value="{{ old('emirates_id_number',$staff->emirates_id_number) }}"></label>
<label>Emirates ID Expiry<input type="date" name="emirates_id_expiry" value="{{ old('emirates_id_expiry',$staff->emirates_id_expiry?->toDateString()) }}"></label>

<label class="span-2">Notes<textarea name="notes">{{ old('notes',$staff->notes) }}</textarea></label>
</div>
<div class="actions"><a class="btn" href="{{ $staff->exists ? route('staff.show',$staff) : route('staff.index') }}">Cancel</a><button class="btn primary">Save</button></div>
</form>
</div>
@endsection
