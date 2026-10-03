<nav x-data="{ open: false }" class="resavar-account-nav">
    <div class="resavar-account-nav__inner">
        <a href="{{ route('dashboard') }}" class="resavar-account-nav__brand" aria-label="Resavar dashboard">
            <img src="{{ asset('images/logo-light.png') }}" alt="Resavar">
        </a>

        <div class="resavar-account-nav__links">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}">Dashboard</a>
            @if(Route::has('user.dashboard'))
                <a href="{{ route('user.dashboard') }}">Guest portal</a>
            @endif
        </div>

        <div class="resavar-account-nav__account">
            <a href="{{ route('profile.edit') }}" class="resavar-account-nav__profile">
                <span>{{ Auth::user()->name }}</span>
                <small>{{ Auth::user()->email }}</small>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="resavar-account-nav__logout">Log out</button>
            </form>
        </div>

        <button type="button" class="resavar-account-nav__toggle" @click="open = !open" :aria-expanded="open.toString()" aria-label="Toggle navigation">
            <span class="material-symbols-outlined" x-text="open ? 'close' : 'menu'">menu</span>
        </button>
    </div>

    <div class="resavar-account-nav__mobile" x-show="open" x-cloak>
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @if(Route::has('user.dashboard'))
            <a href="{{ route('user.dashboard') }}">Guest portal</a>
        @endif
        <a href="{{ route('profile.edit') }}">Profile</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </div>
</nav>
