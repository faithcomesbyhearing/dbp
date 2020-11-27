@extends('layouts.app')

@section('content')

    @include('layouts.partials.banner', [
        'title'     => trans('app.site_name'),
        'subtitle'  => trans('app.site_description'),
        'size'      => 'medium',
        'image'     => '/images/dbp_icon.svg',
        'actions'   => [
            route('docs.getting_started') => 'Get Started'
        ]
    ])



@endsection