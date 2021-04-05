@extends('layouts.apiKey')
@section('head')
<title>Request your API key</title>
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
      <form id="key_request" method="POST" name="wf-form-API-Key-Request" data-name="API Key Request" class="form-single-col" action="{{ route('api_key.request') }}">
        @csrf <!-- add csrf field on your form -->
        <div class="full-col__input-wrapper mb-24">
          <label for="Name" class="default-form__label">Name</label>
          <input type="text" class="default-input w-input" maxlength="256" id="name" name="name" data-name="name" placeholder="Type your name..." id="API-key-request-name" required="" value="{{ old('name') }}">
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="Email" class="default-form__label">Email</label>
          <input type="email" class="default-input w-input" maxlength="256" id="email" name="email" data-name="email" placeholder="Type your email address...." id="API-key-request-email" required="" value="{{ old('email') }}">
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="API-use" class="default-form__label">How will you use the key?</label>
          <textarea placeholder="Please describe how the key will be user, include any relevant URL's" maxlength="5000" id="description" type="text" name="description" data-name="description" class="default-input default-input--text w-input">{{ old('description') }}</textarea>
        </div>
        <div class="full-col__input-wrapper mb-24">
          <label for="API-comments-2" class="default-form__label">Do you have any comments or questions?</label>
          <textarea placeholder="Please describe..." maxlength="5000" id="questions" type="text" name="questions" data-name="questions" class="default-input default-input--text w-input">{{ old('questions') }}</textarea>
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
