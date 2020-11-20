<nav>
    <div>
        <div>
            Digital Bible Platform
        </div>
        <div>
            @if(Auth::user())
            <div>{{ Auth::user()->email }}</div>
            <a href="{{ route('admin.logout') }}">Log Out</a>
            @endif
        </div>
    </div>
</nav>