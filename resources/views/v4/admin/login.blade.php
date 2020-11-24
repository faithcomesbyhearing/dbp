@extends('v4.layouts.app')
@section('head')
<title>Login</title>
@endsection

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.login') }}">
        <p class="card-header">Authentication Required</p>
        <p class = "card-subtitle">The server https://dbp.com/admin requires a username 
            <br> and password</p>
            @if($errors->has('auth.failed'))
                <div>{{ $errors->first('auth.failed') }}</div>
            @endif
        <div class="input-row">
            <label class="login-label" for="email">Username</label>
            <div class="input-icons"> 
                <i class="fa fa-user icon"></i> 
                <input class="input" id="email" type="text" autocomplete="email" name="email" value="{{ old('email') }}" required autofocus placeholder="user@email.com">
            </div>
        </div> 
        <div class="input-row">
            <label class="login-label" for="password">Password</label>
            <div class="input-icons"> 
                <i class="fa fa-lock icon"></i> 
                <input class="input" id="password" type="password" name="password" required placeholder="Please type your password...">
            </div>
        </div> 
            
        <div class="row login-btns">
            <button class="btn btn-success" type="submit">Log In</button>
            <a class="btn btn-cancel" href="{{ route('welcome') }}">Cancel</a>
        </div>
        <input name="_token" value="{{ csrf_token() }}" type="hidden" />
    </form>
</div>

@endsection