@php
    $adminUser = auth()->user();
    $displayName = $adminUser?->name ?: $adminUser?->username ?: 'Administrator';
    $roleLabel = $adminUser?->staff_role
        ? str($adminUser->staff_role)->headline()
        : ((bool) ($adminUser?->is_admin ?? false) ? 'Administrator' : 'Staff');

    $avatarPath = $adminUser?->profile_photo_path ?: $adminUser?->avatar_path;
@endphp

<header class="az-admin-topbar">
    <div class="az-topbar-start">
        <button
            type="button"
            class="az-icon-button az-sidebar-trigger"
            data-sidebar-open
            aria-controls="az-admin-sidebar"
            aria-expanded="false"
            aria-label="Open navigation"
        >
            <span class="material-symbols-outlined" aria-hidden="true">menu</span>
        </button>

        <div class="az-topbar-page">
            <span class="az-topbar-page__eyebrow">@yield('section-label', 'Administration')</span>
            <strong>@yield('title', 'Dashboard')</strong>
        </div>
    </div>

    <div class="az-topbar-actions"><span class="az-s78-operational-timezone">Resavar operational timezone: {{ config('localization.platform_timezone','UTC') }}</span><div class="az-profile-menu" data-profile-menu>
            <button
                type="button"
                class="az-profile-trigger"
                data-profile-trigger
                aria-haspopup="menu"
                aria-expanded="false"
            >
                <span class="az-avatar">
                    @if ($avatarPath)
                        <img src="{{ asset('storage/'.$avatarPath) }}" alt="">
                    @else
                        <span class="az-avatar-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
                    @endif
                </span>

                <span class="az-profile-trigger__copy">
                    <strong>{{ $displayName }}</strong>
                    <small>{{ $roleLabel }}</small>
                </span>

                <span class="material-symbols-outlined az-profile-chevron" aria-hidden="true">
                    expand_more
                </span>
            </button>

            <div class="az-profile-dropdown" data-profile-dropdown role="menu" hidden>
                <div class="az-profile-summary">
                    <span class="az-avatar az-avatar--large">
                        @if ($avatarPath)
                            <img src="{{ asset('storage/'.$avatarPath) }}" alt="">
                        @else
                            {{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}
                        @endif
                    </span>

                    <div>
                        <strong>{{ $displayName }}</strong>
                        <span>{{ $adminUser?->email }}</span>
                    </div>
                </div>

                <div class="az-profile-divider"></div>

                @if ($adminUser?->isAdministrator() && Route::has('azari.admin.staff.edit'))
                    <a href="{{ route('azari.admin.staff.edit', $adminUser) }}" role="menuitem">
                        <span class="material-symbols-outlined" aria-hidden="true">person</span>
                        <span>My staff profile</span>
                    </a>
                @endif

                @if (Route::has('azari.admin.settings.integrations'))
                    <a href="{{ route('azari.admin.settings.integrations') }}" role="menuitem">
                        <span class="material-symbols-outlined" aria-hidden="true">tune</span>
                        <span>Account settings</span>
                    </a>
                @endif

                <div class="az-profile-divider"></div>

                <form method="POST" action="{{ route('azari.admin.logout') }}">
                    @csrf

                    <button
                        type="submit"
                        role="menuitem"
                        class="az-profile-signout"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>