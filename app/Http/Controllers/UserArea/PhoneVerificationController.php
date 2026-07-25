<?php

namespace App\Http\Controllers\UserArea;

use App\Contracts\Communication\SmsProvider;
use App\Http\Controllers\Controller;
use App\Models\PhoneVerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PhoneVerificationController extends Controller
{
    public function send(Request $request, SmsProvider $sms): RedirectResponse
    {
        $user = $request->user();
        if (! config('azari.phone_verification.enabled', false) || ! $sms->enabled()) {
            throw ValidationException::withMessages(['phone' => 'Phone verification is not currently enabled.']);
        }
        if (blank($user->phone)) throw ValidationException::withMessages(['phone' => 'Add a phone number first.']);
        $key = 'phone-verify:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) throw ValidationException::withMessages(['phone' => 'Please wait before requesting another code.']);
        RateLimiter::hit($key, 600);
        $code = (string) random_int(100000, 999999);
        PhoneVerificationCode::query()->where('user_id', $user->id)->whereNull('used_at')->delete();
        PhoneVerificationCode::query()->create(['user_id' => $user->id, 'phone' => $user->phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10)]);
        $sms->send($user->phone, "Your Azari verification code is {$code}. It expires in 10 minutes.");
        return back()->with('success', 'Verification code sent.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $record = PhoneVerificationCode::query()->where('user_id', $request->user()->id)->whereNull('used_at')->where('expires_at', '>', now())->latest()->first();
        if (! $record || $record->attempts >= 5) {
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or expired.']);
        }

        $record->increment('attempts');
        if (! Hash::check($data['code'], $record->code_hash)) {
            if ($record->fresh()->attempts >= 5) {
                $record->update(['used_at' => now()]);
            }
            throw ValidationException::withMessages(['code' => 'The verification code is invalid or expired.']);
        }

        $record->update(['used_at' => now()]);
        $request->user()->update(['phone_verified_at' => now()]);
        return back()->with('success', 'Phone number verified.');
    }
}
