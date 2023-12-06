@extends('layouts.apiKey')
@section('head')
<title>Login</title>
@endsection

@section('content')
<div class="card">
    <form method="POST" action="{{ route('api_key.login') }}">
        <p class="card-header">Authentication Required</p>
            @if($errors->has('auth.failed'))
                <div>{{ $errors->first('auth.failed') }}</div>
            @endif
            @if($errors->has('auth.sessionExpired'))
                <div>{{ $errors->first('auth.sessionExpired') }}</div>
            @endif
        <div class="field">
            <label class="label" for="email">{{ __('Username') }}</label>
            <div class="control"><input class="input" id="email" type="text" autocomplete="email" name="email" value="{{ old('email') }}" required autofocus placeholder="user@email.com"></div>
            @if($errors->has('email')) <p class="help is-danger">{{ $errors->first('email') }}</p> @endif
        </div>
        <div class="field">
            <label class="label" for="password">{{ __('Password') }}</label>
            <div class="control"><input class="input" id="password" type="password" name="password" required placeholder="Please type your password..."></div>
            @if($errors->has('password')) <p class="help is-danger">{{ $errors->first('password') }}</p> @endif
        </div>
        <hr>
        <div class="field is-grouped">
            <div class="control">
                <button type="submit" class="button is-link">{{ __('Login') }}</button>
                <a class="button is-white" href="{{ route('welcome') }}">{{ __('Cancel') }}</a>
            </div>
        </div>
        <input name="_token" value="{{ csrf_token() }}" type="hidden" />
    </form>
</div>

@endsection