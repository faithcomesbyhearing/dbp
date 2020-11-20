@extends('v4.layouts.app')
@section('content')
<form method="POST" action="{{ route('admin.login') }}">
    <div>
        <label for="email">Username</label>
        <input id="email" type="text" autocomplete="email" name="email" value="{{ old('email') }}" required autofocus placeholder="user@email.com">
        @if($errors->has('email')) {{ $errors->first('email') }} @endif
    </div>
    <div>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required placeholder="Password">
        @if($errors->has('password')) {{ $errors->first('password') }} @endif
    </div>
    <div>
        <button type="submit">Log In</button>
        <a href="{{ route('welcome') }}">Cancel</a>
    </div>
    <input name="_token" value="{{ csrf_token() }}" type="hidden" />
</form>
@endsection