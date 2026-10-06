<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\BookingHold;
use App\Models\User;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingCreationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingOnboardingController extends Controller
{
    public function show(
        Request $request,
        string $token,
        AzariPricingEngine $pricing
    ): View|RedirectResponse {
        $hold = $this->hold($token);
        $user = $request->user();

        if ($hold->user_id) {
            if (! $user) {
                $request->session()->put('url.intended', route('azari.booking.checkout', $hold->token));

                return redirect()
                    ->route('login')
                    ->with('status', 'Sign in to continue the booking you already started.');
            }

            abort_unless((int) $hold->user_id === (int) $user->id, 403);
        }

        if ($user) {
            if (! $hold->user_id) {
                $hold->forceFill(['user_id' => $user->id])->save();
            }

            if (! $user->hasVerifiedEmail()) {
                $this->issueEmailCode($hold, $user, false);

                return redirect()->route('azari.booking.onboarding.email', $hold->token);
            }

            if (filled($hold->guest_draft)) {
                return view('public.bookings.finishing', compact('hold'));
            }
        }

        return view('public.bookings.checkout', [
            'hold' => $hold,
            'quote' => $pricing->quote(
                $hold->property,
                $hold->check_in,
                $hold->check_out,
                [],
                $hold->accommodationType,
                $hold->ratePlan,
                max(1, (int) $hold->rooms)
            ),
            'draft' => (array) ($hold->guest_draft ?? []),
            'creatingAccount' => ! $user,
        ]);
    }

    public function begin(
        Request $request,
        string $token,
        BookingCreationService $bookings
    ): RedirectResponse {
        $hold = $this->hold($token);
        $user = $request->user();

        if ($hold->user_id && (! $user || (int) $hold->user_id !== (int) $user->id)) {
            abort(403);
        }

        $draft = $bookings->validateDraft($request, $hold);

        if ($user && strcasecmp((string) $user->email, (string) $draft['guest_email']) !== 0) {
            throw ValidationException::withMessages([
                'guest_email' => 'Use the email address attached to your signed-in Azari account.',
            ]);
        }

        if (! $user) {
            $existing = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $draft['guest_email'])])
                ->first();

            if ($existing) {
                $this->saveDraft($hold, $draft, $existing->id);
                $request->session()->put('url.intended', route('azari.booking.checkout', $hold->token));

                return redirect()
                    ->route('login')
                    ->with('status', 'You already have an Azari account with this email. Sign in and your booking will continue automatically.');
            }

            $credentials = $request->validate([
                'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            ]);

            $user = User::query()->create([
                'name' => trim($draft['first_name'].' '.$draft['last_name']),
                'email' => mb_strtolower((string) $draft['guest_email']),
                'phone' => $draft['guest_phone'],
                'password' => Hash::make($credentials['password']),
                'account_type' => 'customer',
                'status' => 'active',
                'is_active' => true,
                'timezone' => config('localization.platform_timezone', 'UTC'),
            ]);

            Auth::login($user);
            $request->session()->regenerate();
        }

        $this->saveDraft($hold, $draft, $user->id);

        if (! $user->hasVerifiedEmail()) {
            $this->issueEmailCode($hold->fresh(), $user, true);

            return redirect()
                ->route('azari.booking.onboarding.email', $hold->token)
                ->with('success', 'Account created. Enter the six-digit code sent to your email to continue.');
        }

        return redirect()->route('azari.booking.checkout', $hold->token);
    }

    public function email(Request $request, string $token): View|RedirectResponse
    {
        $hold = $this->ownedHold($request, $token);
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('azari.booking.checkout', $hold->token);
        }

        return view('public.bookings.verify-email', [
            'hold' => $hold,
            'email' => $user->email,
        ]);
    }

    public function sendEmailCode(Request $request, string $token): RedirectResponse
    {
        $hold = $this->ownedHold($request, $token);

        $this->issueEmailCode($hold, $request->user(), true);

        return back()->with('success', 'A fresh six-digit verification code has been sent.');
    }

    public function verifyEmailCode(Request $request, string $token): RedirectResponse
    {
        $hold = $this->ownedHold($request, $token);
        $user = $request->user();

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if ($hold->email_code_locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please wait before trying again.',
            ]);
        }

        if (
            blank($hold->email_code_hash)
            || ! $hold->email_code_expires_at
            || $hold->email_code_expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'code' => 'This verification code has expired. Request a new one.',
            ]);
        }

        if (! Hash::check((string) $data['code'], (string) $hold->email_code_hash)) {
            $attempts = ((int) $hold->email_code_attempts) + 1;

            $hold->forceFill([
                'email_code_attempts' => $attempts,
                'email_code_locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null,
            ])->save();

            throw ValidationException::withMessages([
                'code' => 'That verification code is incorrect.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $hold->forceFill([
            'email_code_hash' => null,
            'email_code_expires_at' => null,
            'email_code_attempts' => 0,
            'email_code_locked_until' => null,
        ])->save();

        $request->session()->put('url.intended', route('azari.booking.checkout', $hold->token));

        return redirect()->route('azari.booking.checkout', $hold->token);
    }

    public function complete(
        Request $request,
        string $token,
        BookingCreationService $bookings
    ): RedirectResponse {
        $hold = $this->ownedHold($request, $token);

        abort_unless($request->user()->hasVerifiedEmail(), 403);
        abort_unless(filled($hold->guest_draft), 422, 'Booking details are missing.');

        $request->merge([
            ...(array) $hold->guest_draft,
            'hold_token' => $hold->token,
            'terms' => '1',
        ]);

        $booking = $bookings->create($request);

        $request->session()->put('azari_guest_bookings.'.$booking->reference, true);

        return redirect()
            ->route('azari.booking.review', $booking->reference)
            ->with('success', 'Booking created. You can now review the reservation and continue to payment.');
    }

    private function hold(string $token): BookingHold
    {
        return BookingHold::query()
            ->with('property')
            ->active()
            ->where('token', $token)
            ->firstOrFail();
    }

    private function ownedHold(Request $request, string $token): BookingHold
    {
        $hold = $this->hold($token);

        abort_unless(
            $request->user()
            && $hold->user_id
            && (int) $hold->user_id === (int) $request->user()->id,
            403
        );

        return $hold;
    }

    private function saveDraft(BookingHold $hold, array $draft, int $userId): void
    {
        $hold->forceFill([
            'user_id' => $userId,
            'guest_draft' => $draft,
            'expires_at' => now()->addMinutes(
                max(15, (int) config('azari.booking.onboarding_hold_minutes', 60))
            ),
        ])->save();
    }

    private function issueEmailCode(BookingHold $hold, User $user, bool $force): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if ($hold->email_code_locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Please wait before requesting another code.',
            ]);
        }

        if (
            ! $force
            && filled($hold->email_code_hash)
            && $hold->email_code_expires_at?->isFuture()
        ) {
            return;
        }

        if ($hold->email_code_sent_at?->gt(now()->subSeconds(60))) {
            if ($force) {
                throw ValidationException::withMessages([
                    'code' => 'A verification code was sent recently. Please wait a moment before requesting another.',
                ]);
            }

            return;
        }

        $code = (string) random_int(100000, 999999);

        $hold->forceFill([
            'email_code_hash' => Hash::make($code),
            'email_code_expires_at' => now()->addMinutes(10),
            'email_code_sent_at' => now(),
            'email_code_attempts' => 0,
            'email_code_locked_until' => null,
        ])->save();

        try {
            Mail::send('emails.booking-account-verification-code', [
                'user' => $user,
                'hold' => $hold,
                'code' => $code,
            ], function ($message) use ($user): void {
                $message
                    ->to($user->email)
                    ->subject('Verify your email to continue your Azari booking');
            });
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'code' => 'We could not send the verification email. Please try again.',
            ]);
        }
    }
}
