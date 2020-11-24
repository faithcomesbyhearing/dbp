<!-- site note: this needs to be modified to match current digitalbibleplatform.com/docs -->
<!DOCTYPE html>
<html class="no-js" lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,900" rel="stylesheet">
    <link rel="stylesheet" href="{{ mix('css/app.css') }}" />
    <meta property="og:site_name" content="{{ trans('app.site_name') }}" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">

    <link rel="stylesheet" href="https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/style.css" type="text/css" media="screen, print" />
  <link rel="stylesheet" type="text/css" media="print" href="https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/print.css" />
  <link rel="shortcut icon" href="https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/favicon.ico" />
  <link rel="alternate" type="application/rss+xml" title="Digital Bible Platform RSS Feed" href="https://www.digitalbibleplatform.com/feed/" />
  <link rel="pingback" href="https://www.digitalbibleplatform.com/site/xmlrpc.php" />
    <meta name='robots' content='noindex,follow' />
<link rel="alternate" type="application/rss+xml" title="Digital Bible Platform &raquo; Developer Documentation Comments Feed" href="https://www.digitalbibleplatform.com/docs/feed/" />
<link rel='stylesheet' id='dataTable-css'  href='https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/dataTable.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='bibles-common-css'  href='https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/bibles-common.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='load-mask-css'  href='https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/jquery.loadmask.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='facebox-css'  href='https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/facebox.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='divbox-css'  href='https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/divbox.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='uploadprogressbar-css'  href='https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/uploadprogressbar.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='nyroModal-css'  href='https://www.digitalbibleplatform.com/site/wp-content/themes/dbp/css/nyroModal.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='dbp_radio-css'  href='https://www.digitalbibleplatform.com/site/wp-content/plugins/dbp_dam/radio.css?ver=4.0.1' type='text/css' media='all' />
<link rel='stylesheet' id='wp-syntax-css-css'  href='https://www.digitalbibleplatform.com/site/wp-content/plugins/wp-syntax/css/wp-syntax.css?ver=1.0' type='text/css' media='all' />
<link rel='stylesheet' id='UserAccessManagerAdmin-css'  href='https://www.digitalbibleplatform.com/site/wp-content/plugins/user-access-manager/css/uamAdmin.css?ver=1.0' type='text/css' media='screen' />
<link rel='stylesheet' id='UserAccessManagerLoginForm-css'  href='https://www.digitalbibleplatform.com/site/wp-content/plugins/user-access-manager/css/uamLoginForm.css?ver=1.0' type='text/css' media='screen' />


    @if(env('APP_DEBUG') == 'true')
        <link rel="shortcut icon" href="/favicon_test.ico" type="image/x-icon">
        <link rel="icon" href="/favicon_test.ico" type="image/x-icon">
    @else
        <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
    @endif

    @if(Localization::isLocalizedRoute())
        @foreach(Localization::getLocales() as $localeCode => $properties)
            @if(Route::current()->getLocalization() === $localeCode)
                <meta property="og:locale" content="{{ $localeCode }}" />
            @else
                <meta property="og:locale:alternate" content="{{ $localeCode }}" />
                <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ Localization::getLocaleUrl($localeCode) }}">
            @endif
        @endforeach
    @endif

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @yield('head')
    <style>
        #translation-dropdown .navbar-link {
            text-align: center;
            font-size: 20px;
            margin:0 auto;
            display: block;
            padding: 10px 20px;
        }

        #translation-dropdown .navbar-link:after {
            display: none;
        }
    </style>
    <script>
        var App = {
        	apiParams: {
        		'key': '{{ config('services.bibleIs.key') }}',
                'v': '4',
        	}
        };
    </script>
</head>
<body>
@include('layouts.partials.nav')

<main id="app">
@yield('content')
</main>

<script src="{{ mix('js/app.js') }}"></script>
<script src="{{ mix('js/bulma.js') }}"></script>
@yield('footer')
</body>
</html>
