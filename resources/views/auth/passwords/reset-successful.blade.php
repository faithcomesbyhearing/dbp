@extends('layouts.app')

@section('head')
    <meta http-equiv="refresh" content="0; URL='{{ $reset_path }}'" />
@endsection

@section('content')

    <div class="container">
        <div class="box reset-successful">
            <h2 class="has-text-centered is-size-4">{{ __('auth.reset_successful') }}</h2>
            @if(isset($reset_path))
                <a href="{{ $reset_path }}">{{ __('auth.continue_to') }} {{ $reset_path }}</a>
            @endif
        </div>
    </div>

@endsection
