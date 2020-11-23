<!DOCTYPE html>
<html class="no-js" lang="{{ config('app.locale') }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:site_name" content="{{ trans('app.site_name') }}" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,900" rel="stylesheet">
    <link rel="stylesheet" href="{{ mix('css/v4_app.css') }}" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">
    @if(env('APP_DEBUG') == 'true')
    <link rel="shortcut icon" href="/favicon_test.ico" type="image/x-icon">
    <link rel="icon" href="/favicon_test.ico" type="image/x-icon">
    @else
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    @endif

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @yield('head')
</head>

<body>
    @include('v4.layouts.partials.nav', [
        'image'     => '/images/dbp_icon_v4.svg',
        'image_text'     => trans('app.site_name'),
    ])

    <main id="app">
        @yield('content')
    </main>
    
    <div class="footer">
        @yield('footer')
    </div>
</body>

</html>