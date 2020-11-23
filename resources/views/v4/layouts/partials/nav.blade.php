<nav class="row header-nav {{ Auth::user() ? 'logged-header' : ''  }}">
    <div class="header-img">
        @isset($image) <img src="{{ $image }}"> @endisset
    </div>
    @isset($image_text) <h4 class="image-text"> {{ $image_text }} </h4> @endisset

    @if(Auth::user())
    <div class="row user-header">
        <i class="fa fa-user circle-icon" aria-hidden="true"></i>
        <div>
          <div class='user-email'>{{ Auth::user()->email }}</div>
          <a class="logout-a" href="{{ route('admin.logout') }}">Log Out</a>
        </div>
    </div>
    @endif
</nav>