@extends('layouts.apiKey')

@section('head')
<title>Congratulations</title>
@endsection

@section('content')
<div class="second-bg">
    <div class="card request-card center">
        <p class="card-header request-title">Congratulations!</p>
        <div>
            <img class="congrats-img" src="/images/default-congrats.png">
        </div>
        <div>
            <p class="card-header">Your request was submitted successfully!</p>
            <p class="congrats-content">We will review your request and get back to you within one week. In the mean time feel free to start reading the documentation.</p>

            <a class="btn btn-success btn-link btn-requested" href="{{ route('docs') }}">Get started with the API</a>
        </div>
    </div>
</div>
@endsection