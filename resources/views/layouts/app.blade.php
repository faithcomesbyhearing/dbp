<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    
    <!-- Fonts hosted at Google CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway|Roboto">
    
    <!-- Bible.is CSS -->
    <link rel="stylesheet" href="/css/style.css">
    
    
    <title>Digital Bible Platform</title>
    @yield('head')
  </head>
  <body>
    <div class="wrapper">
      <header>
        <nav class="navbar navbar-expand-lg">
          <a class="navbar-brand" href="{{ route('welcome') }}"><img src="/images/dbp_logo.png"></a>
          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
        
          <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
            @if(Auth::user())
            <li class="nav-item active">
              {{ Auth::user()->email }}
            </li>                 
            <a class="logout-a" href="{{ route('api_key.logout') }}">Log Out</a>
            
          @endif
          </div>
        </nav>
      </header>

      <div class="container">
        <div class="row">
          <!-- Left Side Navigation -->
          <div class="col-3 nav-column">

          @yield('left-nav')

		  </div> <!-- end div col-3 nav-column -->
<!-- there are two open divs that need to be closed after docs content -->
<!-- there is another div for the wrapper that needs to be closed -->

@yield('content')

<!-- close out divs for left nav -->
		</div>
      </div>
    </div>
<!-- end close out divs for left nav -->      


    <footer class="class="container-fluid"">
      <div class="container foot-wrapper">
        <div class="row">
          <div class="col-6 footer-left"> </div>
          <div class="col-6 footer-right">

          </div>
        </div>
      </div>
      
      
    </footer>


    <!-- jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous"></script>
  </body>
</html>          