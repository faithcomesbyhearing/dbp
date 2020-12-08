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
                <label for="agreement">I have read and agreed to the <a href="javascript:showAgreement(true);">DBP License Agreement</a></label>
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
        <div class="agreement-content">
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam. Sed nisi. Nulla quis sem at nibh elementum imperdiet. Duis sagittis ipsum. Praesent mauris. Fusce nec tellus sed augue semper porta. Mauris massa. Vestibulum lacinia arcu eget nulla. </p>

            <p>Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Curabitur sodales ligula in libero. Sed dignissim lacinia nunc. Curabitur tortor. Pellentesque nibh. Aenean quam. In scelerisque sem at dolor. Maecenas mattis. Sed convallis tristique sem. Proin ut ligula vel nunc egestas porttitor. Morbi lectus risus, iaculis vel, suscipit quis, luctus non, massa. Fusce ac turpis quis ligula lacinia aliquet. </p>

            <p>Mauris ipsum. Nulla metus metus, ullamcorper vel, tincidunt sed, euismod in, nibh. Quisque volutpat condimentum velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Nam nec ante. Sed lacinia, urna non tincidunt mattis, tortor neque adipiscing diam, a cursus ipsum ante quis turpis. Nulla facilisi. Ut fringilla. Suspendisse potenti. Nunc feugiat mi a tellus consequat imperdiet. Vestibulum sapien. Proin quam. Etiam ultrices. Suspendisse in justo eu magna luctus suscipit. </p>

            <p>Sed lectus. Integer euismod lacus luctus magna. Quisque cursus, metus vitae pharetra auctor, sem massa mattis sem, at interdum magna augue eget diam. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Morbi lacinia molestie dui. Praesent blandit dolor. Sed non quam. In vel mi sit amet augue congue elementum. Morbi in ipsum sit amet pede facilisis laoreet. Donec lacus nunc, viverra nec, blandit vel, egestas et, augue. Vestibulum tincidunt malesuada tellus. Ut ultrices ultrices enim. Curabitur sit amet mauris. Morbi in dui quis est pulvinar ullamcorper. Nulla facilisi. </p>

            <p>Integer lacinia sollicitudin massa. Cras metus. Sed aliquet risus a tortor. Integer id quam. Morbi mi. Quisque nisl felis, venenatis tristique, dignissim in, ultrices sit amet, augue. Proin sodales libero eget ante. Nulla quam. Aenean laoreet. Vestibulum nisi lectus, commodo ac, facilisis ac, ultricies eu, pede. Ut orci risus, accumsan porttitor, cursus quis, aliquet eget, justo. Sed pretium blandit orci. </p>
        </div>
        <button class="btn btn-success agreement-btn" onclick="acceptAgreement();">I Agree</button>
    </div>
</div>
@endsection