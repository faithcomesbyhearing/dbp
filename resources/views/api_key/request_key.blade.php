@extends('layouts.apiKey')
@section('head')
<title>Request your API key</title>
<script>
    function showAgreement(show) {
        const display = show ? "block" : "none";
        document.getElementById("agreement_modal").style.display = display;
    }

    function acceptAgreement() {
        showAgreement(false);
        document.getElementById("agreement").checked = true;
    }
</script>
<link rel="stylesheet" href="{{ mix('css/app_api_key.css') }}" />
@endsection

@section('content')
<div class="second-bg">
    <div class="card request-card">
        <p class="card-header request-title">Request your API Key</p>
        <form id="key_request" method="POST" action="{{ route('api_key.request') }}">
            <div class="col input-request">
                <label for="name">Name</label>
                <input class="input no-icon-input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Type your name...">
                @if($errors->has('name')) <p>{{ $errors->first('name') }}</p> @endif
            </div>
            <div class="col input-request">
                <label for="email">E-mail Address</label>
                <input class="input no-icon-input" id="email" type="text" autocomplete="email" name="email" value="{{ old('email') }}" required placeholder="Type your e-mail address...">
                @if($errors->has('email')) <p>{{ $errors->first('email') }}</p> @endif
            </div>
            <div class="col input-request">
                <label for="description">How will you use the key?</label>
                <textarea class="input no-icon-input" id="description" type="text" name="description" required placeholder="Please describe how the key will be user, include any relevant URL's">{{ old('description') }}</textarea>
                @if($errors->has('description')) <p>{{ $errors->first('description') }}</p> @endif
            </div>
            <div class="col input-request last">
                <label for="questions">Do you have any comments or questions?</label>
                <textarea class="input no-icon-input" id="questions" type="text" name="questions" placeholder="Please describe...">{{ old('questions') }}</textarea>
                @if($errors->has('questions')) <p>{{ $errors->first('questions') }}</p> @endif
            </div>
            <div class="input-request checkbox">
                <input id="agreement" name="agreement" type="checkbox" {{ old('agreement') ? 'checked' : '' }} required>
                <label for="agreement">I have read and agreed to the <a href="https://www.biblebrain.com/license"  target="_blank">DBP License Agreement</a></label>
                @if($errors->has('agreement')) <p>{{ $errors->first('agreement') }}</p> @endif
            </div>
            <div class="col input-request">
                <button class="btn btn-success btn-request" type="submit">Submit</button>
            </div>
            <input name="_token" value="{{ csrf_token() }}" type="hidden" />
        </form>
    </div>
</div>

<div class="modal-container" id="agreement_modal" >
    <div class="card agreement-modal">
        <div class="agreement-header">
            <a href="#" class="close" onclick="showAgreement(false);"></a>
            <p class="card-header agreement-title">Digital Bible Platform License Agreement</p>
        </div>

        <button class="btn btn-success agreement-btn" onclick="acceptAgreement();">I Agree</button>
    </div>
</div>
@endsection