@extends('layouts.user')
@section('title','Additional guests')
@section('kicker','Booking identities')
@section('page_title','Additional guests')

@section('content')
<div class="az-user-restricted-note">
    Every additional adult verifies themselves. Resavar sends each person a private path-based link and requires an email code before their details or Dojah flow can be opened.
</div>

<section class="az-user-panel" style="margin-top:18px">
<header class="az-user-panel-header">
    <div>
        <h2 class="az-user-panel-title">Additional adult verification</h2>
        <p class="az-user-panel-subtitle">Send, resend or copy each adult's verification link</p>
    </div>
</header>

<div class="az-user-panel-body">
@if($guests->isEmpty())
    <div class="az-user-empty">
        <span class="material-symbols-outlined">group</span>
        <p>No additional adults are currently attached to your bookings.</p>
    </div>
@else
    <div class="az-user-list">
    @foreach($guests as $guest)
        @php
            $verified = $guest->latestIdentityVerification?->isVerified() ?? false;
            $verificationUrl = url('/guest-verification/'.$guest->booking->reference.'/'.$guest->position);
        @endphp

        <article class="az-user-list-item">
            <div>
                <h3>{{ $guest->full_name }} · {{ $guest->booking?->reference }}</h3>
                <p>
                    {{ $guest->booking?->property?->name }}
                    · {{ $guest->email }}
                    · {{ $verified ? 'Dojah verified' : 'Verification required' }}
                    @if($guest->user_id) · Resavar account linked @endif
                </p>

                <div style="margin-top:8px;word-break:break-all">
                    <small>{{ $verificationUrl }}</small>
                </div>
            </div>

            <div class="az-user-actions">
                <span class="az-user-status {{ $verified ? '' : 'az-user-status--warning' }}">
                    {{ $verified ? 'Verified' : 'Pending' }}
                </span>

                @unless($verified)
                    <form method="POST" action="{{ route('user.guests.verification-invite.send',[$guest->booking->reference,$guest]) }}">
                        @csrf
                        <button class="az-user-button az-user-button--dark" type="submit">
                            {{ $guest->verificationInvite?->invite_sent_at ? 'Resend link' : 'Send link' }}
                        </button>
                    </form>
                @endunless

                <button
                    class="az-user-button az-user-button--light"
                    type="button"
                    data-copy-guest-link="{{ $verificationUrl }}"
                >
                    Copy link
                </button>
            </div>
        </article>
    @endforeach
    </div>

    {{ $guests->links() }}
@endif
</div>
</section>

<script>
document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-copy-guest-link]');
    if (!button) return;

    const link = button.getAttribute('data-copy-guest-link');

    try {
        await navigator.clipboard.writeText(link);
        const previous = button.textContent;
        button.textContent = 'Copied';
        setTimeout(() => button.textContent = previous, 1600);
    } catch (_) {
        window.prompt('Copy this verification link:', link);
    }
});
</script>
@endsection
