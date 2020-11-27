@extends('layouts.docs')


@section('docs-content')
    <h3>Developer Documentation</h3>
    <h2>Everything you need to know when working with the Digital Bible Platform API.</h2>

    <p><a href="{{ route('core_concepts')}}"></p>
    <div class="box">
        <h4>Core Concepts</h4>
        <p>Provides some of the fundamental concepts for understanding how to interface with the DBP.</p>
    </div>
    <p><a href="{{ route('available_content')}}"></p>
    <div class="box">
        <h4>Available Content</h4>
        <p>Highlights the content available with an API Key</p>
    </div>    <p><a href="{{ route('api_reference')}}"></p>
    <div class="box">
        <h4>API Reference</h4>
        <p>OpenAPI documentation on how to access the content</p>
    </div>    <p><a href="{{ route('user_flows')}}"></p>
    <div class="box">
        <h4>User Flows</h4>
        <p>Example Flows typical in applications</p>
    </div>
@endsection