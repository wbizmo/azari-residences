<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\Identity\DojahService;
use App\Services\Identity\GuestVerificationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PublicGuestVerificationController extends Controller
{
    public function show(
        Request $request,
        string $reference,
        int $position,
        DojahService $dojah
    ): View {
        [$booking, $guest] = $this->guest($reference, $position);

        if (! $this->hasGrant($request, $guest)) {
            return view('public.guest-verification.show', [
                'stage' => 'email',
                'booking' => $booking,
                'guest' => null,
                'position' => $position,
                'verification' => null,
                'widgetConfigured' => false,
                'widget' => null,
                'accountState' => null,
                'allAdultsVerified' => false,
            ]);
        }

        $authenticated = $request->user();

        if (
            $authenticated
            && strcasecmp((string) $authenticated->email, (string) $guest->email) === 0
            && ! $guest->user_id
        ) {
            $guest->forceFill(['user_id' => $authenticated->id])->save();
        }

        if ($guest->user_id) {
            $linkedUser = User::query()->find($guest->user_id);

            if ($linkedUser && IdentityVerification::userIsVerified((int) $linkedUser->id)) {
                $dojah->syncVerifiedUserToGuest($linkedUser, $guest);
            }
        }

        $latest = $dojah->latestForGuest($guest);
        $verification = $latest?->isVerified() ? $latest : $dojah->verificationForGuest($guest);

        $existingAccount = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $guest->email)])
            ->first();

        $accountState = match (true) {
            (bool) $guest->user_id => 'linked',
            (bool) $existingAccount => 'existing',
            default => 'available',
        };

        $adultGuests = $booking->guests()
            ->where('type', 'adult')
            ->get();

        $allAdultsVerified = $adultGuests->isNotEmpty()
            && $adultGuests->every(
                fn (BookingGuest $adult) =>
                    IdentityVerification::guestIsVerified((int) $adult->id)
            );

        return view('public.guest-verification.show', [
            'stage' => 'identity',
            'booking' => $booking,
            'guest' => $guest,
            'position' => $position,
            'verification' => $verification,
            'widgetConfigured' => $dojah->widgetConfigured(),
            'widget' => $dojah->widgetPayload($verification, [
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'email' => $guest->email,
            ]),
            'accountState' => $accountState,
            'allAdultsVerified' => $allAdultsVerified,
        ]);
    }

    public function sendCode(
        string $reference,
        int $position,
        GuestVerificationInvitationService $invitations
    ): RedirectResponse {
        [, $guest] = $this->guest($reference, $position);

        $invitations->sendCode($guest);

        return back()->with(
            'success',
            'A six-digit verification code was sent to the email address supplied for this guest.'
        );
    }

    public function verifyCode(
        Request $request,
        string $reference,
        int $position,
        GuestVerificationInvitationService $invitations
    ): RedirectResponse {
        [, $guest] = $this->guest($reference, $position);

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! $invitations->verifyCode($guest, (string) $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That verification code is incorrect.',
            ]);
        }

        $request->session()->put(
            $this->grantKey($guest),
            now()->addMinutes(30)->timestamp
        );

        return redirect()->route('guest-verification.show', [
            $reference,
            $position,
        ])->with('success', 'Email confirmed. You can now complete your own identity verification.');
    }

    public function status(
        Request $request,
        string $reference,
        int $position
    ): JsonResponse {
        [$booking, $guest] = $this->guest($reference, $position);

        $this->requireGrant($request, $guest);

        $adultGuests = $booking->guests()
            ->where('type', 'adult')
            ->get();

        return response()->json([
            'verified' => IdentityVerification::guestIsVerified((int) $guest->id),
            'all_adults_verified' => $adultGuests->isNotEmpty()
                && $adultGuests->every(
                    fn (BookingGuest $adult) =>
                        IdentityVerification::guestIsVerified((int) $adult->id)
                ),
        ]);
    }

    public function createAccount(
        Request $request,
        string $reference,
        int $position,
        DojahService $dojah
    ): RedirectResponse {
        [, $guest] = $this->guest($reference, $position);

        $this->requireGrant($request, $guest);

        if ($request->user() && strcasecmp((string) $request->user()->email, (string) $guest->email) !== 0) {
            throw ValidationException::withMessages([
                'account' => 'Sign out of the other Azari account before creating an account for this guest.',
            ]);
        }

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $guest->email)])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'account' => 'An Azari account already exists for this email. Sign in to link it instead.',
            ]);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        // Security-sensitive attributes are guarded by User::$fillable.
        // Use forceFill for these server-verified values only after the
        // signed-invitation email-code session grant has succeeded.
        $user = new User;
        $user->fill([
            'name' => $guest->full_name,
            'email' => mb_strtolower((string) $guest->email),
            'password' => Hash::make($data['password']),
            'timezone' => config('localization.platform_timezone', 'UTC'),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'account_type' => 'customer',
            'status' => 'active',
            'is_active' => true,
        ])->save();

        $guest->forceFill(['user_id' => $user->id])->save();

        if ($dojah->latestForGuest($guest)?->isVerified()) {
            $dojah->syncVerifiedGuestToUser($guest, $user);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('guest-verification.show', [
            $reference,
            $position,
        ])->with('success', 'Your Azari account was created and linked to this guest verification.');
    }

    public function linkAccount(
        Request $request,
        string $reference,
        int $position,
        DojahService $dojah
    ): RedirectResponse {
        [, $guest] = $this->guest($reference, $position);

        $this->requireGrant($request, $guest);

        if (! $request->user()) {
            $request->session()->put('url.intended', route('guest-verification.show', [
                $reference,
                $position,
            ]));

            return redirect()
                ->route('login')
                ->with('status', 'Sign in to link your existing Azari account to this guest verification.');
        }

        if (strcasecmp((string) $request->user()->email, (string) $guest->email) !== 0) {
            throw ValidationException::withMessages([
                'account' => 'The signed-in account email does not match the guest email for this invitation.',
            ]);
        }

        $guest->forceFill(['user_id' => $request->user()->id])->save();

        if (IdentityVerification::userIsVerified((int) $request->user()->id)) {
            $dojah->syncVerifiedUserToGuest($request->user(), $guest);
        } elseif ($dojah->latestForGuest($guest)?->isVerified()) {
            $dojah->syncVerifiedGuestToUser($guest, $request->user());
        }

        return redirect()->route('guest-verification.show', [
            $reference,
            $position,
        ])->with('success', 'Your Azari account is now linked to this guest verification.');
    }

    private function guest(string $reference, int $position): array
    {
        $booking = Booking::query()
            ->with('property')
            ->where('reference', $reference)
            ->firstOrFail();

        $guest = $booking->guests()
            ->where('type', 'adult')
            ->where('position', $position)
            ->where('is_lead', false)
            ->firstOrFail();

        return [$booking, $guest];
    }

    private function grantKey(BookingGuest $guest): string
    {
        return 'azari_guest_verification_grants.'.$guest->id;
    }

    private function hasGrant(Request $request, BookingGuest $guest): bool
    {
        return (int) $request->session()->get($this->grantKey($guest), 0) > now()->timestamp;
    }

    private function requireGrant(Request $request, BookingGuest $guest): void
    {
        abort_unless($this->hasGrant($request, $guest), 403);
    }
}
