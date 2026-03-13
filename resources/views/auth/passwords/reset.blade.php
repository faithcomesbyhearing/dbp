@extends('layouts.app')
@section('head')
<script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
<script>
    $(document).ready(function () {
        var msgSuccess  = @json(trans('auth.password_reset_success'));
        var msgMinLength = @json(trans('auth.password_min_length'));
        var msgMismatch  = @json(trans('auth.passwords_do_not_match'));
        var form = $('#password-reset');
        form.submit(function (e) {
            e.preventDefault();
            var valid = Validate();
            if (valid) {
                $.ajax({
                    type: form.attr('method'),
                    url:  form.attr('action'),
                    data: form.serialize(),
                    dataType: 'json',
                    success: function (data) {
                        $( "#message-box" ).prepend('<div class="alert alert-success has-text-centered">' + msgSuccess + '</div>');
                        window.location.href = "https://www.faithcomesbyhearing.com/bible-brain";
                    }
                });
            }
        });
        function Validate() {
            $( "#error-box" ).empty();
            if ($("#password").val().length < 8) {
                $( "#message-box" ).prepend('<div class="alert alert-error has-text-centered">' + msgMinLength + '</div>');
                return false;
            }
            if($("#password").val() != $("#password-confirm").val()) {
                $( "#message-box" ).prepend('<div class="alert alert-error has-text-centered">' + msgMismatch + '</div>');
                return false;
            }
            return true;
        }
    });
</script>
@endsection

@section('content')
<div class="api-form-container">
    <div role="banner" class="hero-default hero-default--bible-brain">
      <div class="hero-default__text mt-0" style="opacity: 1; transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d;">
        <h1 class="txt-h2">{{ __('auth.resetPassword') }}</h1>
      </div>
    </div>
    <form id="password-reset" class="column is-half is-offset-one-quarter" method="POST" action="{{ route('v4_internal_user.password_reset', ['token' => $reset_request->token,'v' => 4, 'key' => 'tighten_37518dau8gb891ub']) }}">
        @csrf
        <div id="message-box"></div>
        <input type="hidden" name="token_id" value="{{ $reset_request->token }}">
        <input type="hidden" name="email" value="{{ $reset_request->email ?? '' }}">

        <div class="full-col__input-wrapper mb-20">
            <label class="default-form__label" for="new_password">{{ __('auth.password') }}</label>
            <input type="password" class="default-input w-input" maxlength="256" id="password" name="new_password" data-name="new_password" placeholder="{{ __('auth.ph_password') }}" required value="{{ old('new_password') }}">
            @if($errors->has('new_password')) <span class="help is-danger"><strong>{{ $errors->first('new_password') }}</strong></span> @endif
        </div>
        <div class="full-col__input-wrapper mb-20">
            <label class="default-form__label" for="password-confirm">{{ __('auth.confirmPassword') }}</label>
            <input type="password" class="default-input w-input" maxlength="256" id="password-confirm" name="new_password_confirmation" data-name="new_password_confirmation" placeholder="{{ __('auth.ph_password_conf') }}" required value="{{ old('new_password_confirmation') }}">
            @if($errors->has('new_password_confirmation'))<span class="help is-danger"><strong>{{ $errors->first('new_password_confirmation') }}</strong></span>@endif
        </div>

        <div class="full-col__input-wrapper align-center">
            <input type="submit" value="{{ __('auth.resetPassword') }}" data-wait="{{ __('auth.please_wait') }}" class="btn-md btn--send mb-40 w-button"/>
        </div>
    </form>
</div>
@endsection
