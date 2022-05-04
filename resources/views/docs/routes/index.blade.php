@extends('layouts.docs')
     
@section('content')
  <!-- Right Content Area -->
  <div class="col-9 content-column">
    <h2>Developer Documentation</h2>
    <p class="txt-intro">Everything you need to know when working with the Digital Bible Platform API</p>

    <div class="row">
      <div class="col-8"><p class="txt-subtitle">Before you begin building with the DBP you’ll need to ensure you have an API Key.</p></div>
      <div class="col-4"><a href="{{ route('api_key.request') }}" class="btn btn-lg">Get Your API Key</a></div>
    </div>

  </div> <!-- end div col-9 content-column -->
@endsection
