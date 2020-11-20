@extends('v4.layouts.app')
@section('head')
<title>Login</title>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.login') }}">
    <h2>Authentication Required</h2>
    <p>The server requires an username and password</p>
    @if($errors->has('auth.failed'))
    <div>{{ $errors->first('auth.failed') }}</div>
    @endif
    <div>
        <label for="email">Username</label>
        <input id="email" type="text" autocomplete="email" name="email" value="{{ old('email') }}" required autofocus placeholder="user@email.com">
    </div>
    <div>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required placeholder="Password">
    </div>
    <div>
        <button type="submit">Log In</button>
        <a href="{{ route('welcome') }}">Cancel</a>
    </div>
    <input name="_token" value="{{ csrf_token() }}" type="hidden" />
</form>
@endsection