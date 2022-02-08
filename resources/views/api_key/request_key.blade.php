@extends('layouts.apiKey')
@section('head')
<title>Request your API key</title>
{!! htmlScriptTagJsApi(['lang' => 'en']) !!}
@endsection

@section('content')
<div role="banner" class="hero-default hero-default--bible-brain">
  <div class="hero-default__text mt-0" style="opacity: 1; transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d;">
    <h1 class="txt-h2">Request Your API Key</h1>
  </div>
</div>
<div class="section">
  <div class="api-form-container">
    <div class="form-single-col__container w-form">
      @if($errors->has('g-recaptcha-response'))
        <div class="full-col__input-wrapper mb-24">
          <p class="card-header notification is-danger is-danger is-light">Captcha Required</p>
        </div>
      @endif
      <form id="key_request" method="POST" name="wf-form-API-Key-Request" data-name="API Key Request" class="form-single-col" action="{{ route('api_key.request') }}">
        @csrf <!-- add csrf field on your form -->
        <div class="full-col__input-wrapper mb-24">
          <label for="API-key-request-name" class="default-form__label">Name</label>
          <input type="text" class="default-input w-input" maxlength="256" name="name" data-name="name" placeholder="Type your name..." id="API-key-request-name" required="" value="{{ old('name') }}">
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="API-key-request-email" class="default-form__label">Email</label>
          <input type="email" class="default-input w-input" maxlength="256" name="email" data-name="email" placeholder="Type your email address...." id="API-key-request-email" required="" value="{{ old('email') }}">
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="API-key-request-description" class="default-form__label">How will you use the key?</label>
          <textarea placeholder="Please describe how the key will be user, include any relevant URL's" maxlength="5000" id="API-key-request-description" type="text" name="description" data-name="description" class="default-input default-input--text w-input">{{ old('description') }}</textarea>
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="API-key-request-application-name" class="default-form__label">Application Name</label>
          <input type="text" class="default-input w-input" maxlength="256" name="application_name" data-name="application-name" placeholder="Type your application name..." id="API-key-request-application-name" required="" value="{{ old('application_name') }}">
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="API-key-request-application-url" class="default-form__label">Application URL</label>
          <textarea type="text" class="default-input w-input" maxlength="512" name="application_url" data-name="application-url" placeholder="Type your application url..." id="API-key-request-application-url" required="">{{ old('application_url') }}</textarea>
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="API-key-request-questions" class="default-form__label">Do you have any comments or questions?</label>
          <textarea placeholder="Please describe..." maxlength="5000" id="API-key-request-questions" type="text" name="questions" data-name="questions" class="default-input default-input--text w-input">{{ old('questions') }}</textarea>
        </div>
        <div class="full-col__input-wrapper">
          <label class="w-checkbox default-checkbox__container">
            <input id="agreement" name="agreement" type="checkbox" data-name="agreement" class="w-checkbox-input default-checkbox default-checkbox--visible" required="">
            <span for="Receive Email Updates" class="txt-sm pl-16 w-form-label">
              <br>I have read and agreed to the<strong> </strong>
              <a href="https://www.faithcomesbyhearing.com/bible-brain/license" target="_blank" class="txt-link">DBP License Agreement</a>
              <br>‍
            </span>
          </label>
        </div>
        @if($errors->has('g-recaptcha-response'))
          <div class="full-col__input-wrapper align-center mb-24">
            <span class="help-block notification is-danger is-light">
              Error verifying Captcha, please try again.
            </span>
          </div>
        @endif
        <div class="full-col__input-wrapper align-center mb-24">
          {!! htmlFormSnippet() !!}
        </div>
        <div class="full-col__input-wrapper align-center">
          <input type="submit" value="Submit" data-wait="Please wait..." class="btn-md btn--send mb-40 w-button">
        </div>
      </form>
      <div class="success w-form-done">
        <div class="txt-md">Your download will start shortly, please wait. If it doesn't start click <a href="#">here.</a></div>
      </div>
      <div class="error-message w-form-fail">
        <div class="txt-deep-red txt-center">Oops! Something went wrong while submitting the form.</div>
      </div>
    </div>
  </div>
</div>

@endsection
