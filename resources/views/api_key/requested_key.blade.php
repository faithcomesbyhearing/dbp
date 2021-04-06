@extends('layouts.apiKey')

@section('head')
<title>Congratulations</title>
@endsection

@section('content')
<div role="banner" class="hero-default hero-default--bible-brain">
  <div class="hero-default__text mt-0" style="opacity: 1; transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg); transform-style: preserve-3d;">
    <h1 class="txt-h2">Success!</h1>
    <div class="txt-intro">We will review your request and get back to you within one week. In the mean time feel free to start reading the documentation.<br></div>
  </div>
</div>
<div class="section">
  <div class="api-form-container txt-center">
    <a href="https://www.faithcomesbyhearing.com/bible-brain/developer-documentation" class="btn-md btn-md--no-margin-x w-button">Get Started with The api</a>
  </div>
</div>
@endsection