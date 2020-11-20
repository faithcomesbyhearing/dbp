<nav>
    <div>
        <div class="navbar-start">
            Digital Bible Platform
        </div>
        <div class="navbar-end">
            @if(Auth::user())
            <div>{{ Auth::user()->email }}</div>
            <a href="{{ route('admin.logout') }}">Log Out</a>
            @endif
        </div>
</nav>