<div
    class="drawer-layer"
    id="mobile-navigation"
    data-drawer
    hidden
    aria-hidden="true"
>
    <div class="drawer-backdrop" data-drawer-close></div>

    <aside class="drawer-panel" role="dialog" aria-modal="true" aria-label="Mobile navigation">
        <div class="drawer-header">
            <a href="{{ route('home') }}" class="brand brand-dark">
                <span class="brand-logo-slot" aria-hidden="true">
                    <span class="material-symbols-outlined">hotel_class</span>
                </span>
                <span class="brand-copy">
                    <strong>Azari</strong>
                    <small>Residences</small>
                </span>
            </a>

            <button type="button" class="modal-close" data-drawer-close aria-label="Close navigation">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <nav class="mobile-navigation" aria-label="Mobile navigation">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('home') }}#residences">Apartments and rooms</a>
            <a href="{{ route('home') }}#availability">Check availability</a>
            <a href="{{ route('home') }}#services">Services</a>
            <a href="{{ route('home') }}#guide">Local guide</a>
            <a href="{{ route('home') }}#about">About Azari</a>
            <a href="{{ route('home') }}#contact">Contact</a>
            <button type="button" data-modal-open="verification-modal" data-drawer-close>
                Verify booking
            </button>
        </nav>

        <div class="drawer-actions">
            @auth
                <a href="{{ route('dashboard') }}" class="button button-primary button-block">Guest dashboard</a>
            @else
                <a href="{{ route('login') }}" class="button button-secondary button-block">Login</a>
                <a href="{{ route('register') }}" class="button button-primary button-block">Create account</a>
            @endauth
        </div>
    </aside>
</div>
