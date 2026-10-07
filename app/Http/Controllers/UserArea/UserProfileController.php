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

    public function preferences(Request $request): RedirectResponse
    {
        $request->validate([
            'email_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
            'whatsapp_notifications' => ['nullable', 'boolean'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        $email = $request->boolean('email_notifications');
        $sms = $request->boolean('sms_notifications');
        $whatsapp = $request->boolean('whatsapp_notifications');
        $marketing = $request->boolean('marketing_consent');

        $user->update([
            'email_notifications' => $email,
            'sms_notifications' => $sms,
            'whatsapp_notifications' => $whatsapp,
            'marketing_consent' => $marketing,
        ]);

        CommunicationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'email_transactional' => $email,
                'sms_transactional' => $sms,
                'whatsapp_transactional' => $whatsapp,
                'in_app_transactional' => true,
                'email_marketing' => $marketing && $email,
                'sms_marketing' => $marketing && $sms,
                'whatsapp_marketing' => $marketing && $whatsapp,
                'locale' => $user->locale ?: app()->getLocale(),
                'timezone' => $user->timezone,
            ]
        );

        return back()->with('success', __('resarva.account.preferences_updated'));
    }
}
