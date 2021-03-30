<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    
    <!-- Fonts hosted at Google CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway|Roboto">
    
    <!-- Bible.is CSS -->
    <link rel="stylesheet" href="/css/style.css">
    
    <!-- Bible brain CSS -->
    <link href="https://assets.website-files.com/5e73b0590a912b0d2533e44f/css/fcbh.b94faf5c0.min.css" rel="stylesheet" type="text/css">
    
    <title>API Key Request - Bible Brain</title>
    <link href="https://assets.website-files.com/5e73b0590a912b0d2533e44f/5f40222b2dc091e13df7d4b9_favicon.png" rel="shortcut icon" type="image/x-icon">
    @yield('head')
  </head>
  <body style="opacity: 1;" class="fcbh" data-new-gr-c-s-check-loaded="14.1001.0" data-gr-ext-installed>
    <div class="default-width-container">
      <section class="dbp-header pt-40 pb-60">
        <a href="https://www.faithcomesbyhearing.com/bible-brain" class="dbp-header__link w-inline-block">
          <img src="https://assets.website-files.com/5e73b0590a912b0d2533e44f/604fa88733da41cfc9530358_BibleBrainFull.svg" loading="lazy" alt="Bible Brain logo" class="dbp-header__link-img" data-src="https://assets.website-files.com/5e73b0590a912b0d2533e44f/604fa88733da41cfc9530358_BibleBrainFull.svg">
        </a>
        <div class="dbp-header__buttons">
          <div class="txt-h5 dbp-header__section-name">API Key Request</div>
        </div>
      </section>
    </div>

    <div class="container">
        <!-- Left Side Navigation -->
        <div class="col-3 nav-column">
          @yield('left-nav')
        </div> <!-- end div col-3 nav-column -->
        @yield('content')
    </div>


    <footer>
      <div class="default-width-container">
        <section class="dbp-footer pt-60 pb-24">
          <a href="https://www.faithcomesbyhearing.com/bible-brain" class="dbp-footer__logo w-inline-block">
            <img src="https://assets.website-files.com/5e73b0590a912b0d2533e44f/604fa88733da41cfc9530358_BibleBrainFull.svg" loading="lazy" alt="Bible Brain logo" class="dbp-footer__logo-img" data-src="https://assets.website-files.com/5e73b0590a912b0d2533e44f/604fa88733da41cfc9530358_BibleBrainFull.svg">
          </a>
          <div class="dbp-footer__links">
            <a href="https://www.faithcomesbyhearing.com/" target="_blank" class="txt-sm txt-link dbp-footer__link">© 2021 Faith Comes By Hearing</a>
            <a href="https://www.faithcomesbyhearing.com/bible-brain/legal" class="txt-sm txt-link dbp-footer__link">Legal</a>
            <a href="mailto:support@digitalbibleplatform.com?subject=DBP%20Contact" class="txt-sm txt-link dbp-footer__link dbp-footer__link--last">Support</a>
          </div>
        </section>
      </div>
    </footer>


    <!-- jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous"></script>
  </body>
</html>          