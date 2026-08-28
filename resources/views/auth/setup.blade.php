@extends('layouts.guest')
@section('title','Create Owner account')
@section('content')
<div class="setup-note"><b>Final installation step</b><p>Create the first Owner. This page permanently closes after the account is created.</p></div><form method="post" action="{{ route('setup.store') }}" class="stack">@csrf<label>Full name<input name="name" value="{{ old('name') }}" required autofocus></label><label>Email<input type="email" name="email" value="{{ old('email') }}" required></label><label>Password <small>Minimum 12 characters</small><input type="password" name="password" required minlength="12"></label><label>Confirm password<input type="password" name="password_confirmation" required minlength="12"></label><button class="btn primary full">Create Owner & open ERP</button></form>
@endsection
