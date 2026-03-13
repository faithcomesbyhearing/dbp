@extends('layouts.app')

@section('head')
    <style>
        .message {
            max-width:400px;
            margin:0 auto;
            text-align: center;
        }
    </style>
@endsection

@section('content')

    {{-- Narsil --}}

    @include('layouts.partials.banner', ['title' => trans('auth.error_title')])

    <div class="container">
        <div class="content">

            <div class="message is-danger">
                @isset($status)
                    <div class="message-header">{{ trans('api.errors_'.$status) }}</div>
                @else
                    <div class="message-header">{{ trans('auth.error_title') }}</div>
                @endif
                <div class="message-body">
                    @if(isset($message)) <p>{{ $message }}</p> @endif
                    @if(isset($hint)) <p>{{ $hint }}</p> @endif
                    @if(isset($action_url))
                        <p><a href="{{ $action_url }}" class="button is-link">{{ $action_label ?? trans('auth.continue') }}</a></p>
                    @endif
                </div>
            </div>

        </div>
    </div>

@endsection