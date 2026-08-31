@extends('layouts.guest')
@section('title','Sign in')
@section('content')
<form method="post" action="{{ route('login.store') }}" class="stack">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label><button class="btn primary full">Sign in</button></form>
@endsection
