<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CommunicationPreference;
use App\Services\Communication\PhoneNumberNormalizer;
use App\Support\AuthAbuseGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    public function edit(Request $request): View { return view('user.profile.edit', ['user' => $request->user()]); }

    public function update(Request $request, PhoneNumberNormalizer $numbers, AuthAbuseGuard $abuse): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'timezone:all'],
            'locale' => ['nullable', Rule::in(array_keys((array) config('localization.supported_locales', ['en'=>'English'])))],
            'display_currency' => ['nullable', Rule::in(array_keys((array) config('localization.supported_currencies', ['USD'=>'US Dollar'])))],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $abuse->assertEmailAllowed((string) $data['email']);

        try {
            $data['phone'] = $numbers->normalize($data['phone'] ?? null);
            $data['emergency_contact_phone'] = $numbers->normalize($data['emergency_contact_phone'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }

        $old = $user->only(['name', 'email', 'phone', 'timezone', 'locale', 'display_currency']);
        if ($request->hasFile('profile_image')) {
            if ($user->profile_photo_path) Storage::disk('public')->delete($user->profile_photo_path);
            $data['profile_photo_path'] = $request->file('profile_image')->store('profiles/customers', 'public');
        }
        unset($data['profile_image']);
        $emailChanged = $data['email'] !== $user->email;
        $phoneChanged = ($data['phone'] ?? null) !== $user->phone;
        $user->fill($data);
        if ($emailChanged) $user->forceFill(['email_verified_at' => null, 'status' => 'pending_verification']);
        if ($phoneChanged) $user->forceFill(['phone_verified_at' => null]);
        $user->save();
        AuditLog::record('user.profile_updated', $user, $old, $user->only(array_keys($old)));
        return back()->with('success', __('resarva.account.profile_updated'));
    }

    /**
     * Display preference only. Never mutate historical booking/payment/refund
     * currencies, or present a converted total without a verified FX quote.
     */
    public function currency(Request $request): RedirectResponse
    {
        $supported = array_keys((array) config('localization.supported_currencies', ['USD' => 'US Dollar']));
        $validated = $request->validate([
            'display_currency' => ['required', 'string', Rule::in($supported)],
        ]);

        $user = $request->user();
        $previous = (string) ($user->display_currency ?: config('localization.default_currency', 'USD'));
        $selected = (string) $validated['display_currency'];

        if ($previous !== $selected) {
            $user->forceFill(['display_currency' => $selected])->save();
            AuditLog::record('user.display_currency_changed', $user,
                ['display_currency' => $previous], ['display_currency' => $selected]);
        }

        return back()->with('success',
            'Currency preference saved. Existing bookings and receipts keep their actual payment currency.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $request->validate([
            'email_notifications' => ['nullable', 'boolean'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        $email = $request->boolean('email_notifications');
        $marketing = $request->boolean('marketing_consent');

        $user->update([
            'email_notifications' => $email,
            'sms_notifications' => false,
            'whatsapp_notifications' => false,
            'marketing_consent' => $marketing,
        ]);

        CommunicationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'email_transactional' => $email,
                'sms_transactional' => false,
                'whatsapp_transactional' => false,
                'in_app_transactional' => true,
                'email_marketing' => $marketing && $email,
                'sms_marketing' => false,
                'whatsapp_marketing' => false,
                'locale' => $user->locale ?: app()->getLocale(),
                'timezone' => $user->timezone,
            ]
        );

        if (! $marketing) {
            app(\App\Services\PhaseThree\LifecycleCampaignService::class)->suppressOnOptOut($user);
        }

        return back()->with('success', __('resarva.account.preferences_updated'));
    }
}
