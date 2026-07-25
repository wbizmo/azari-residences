<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    public function edit(Request $request): View { return view('user.profile.edit', ['user' => $request->user()]); }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'timezone:all'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $old = $user->only(['name', 'email', 'phone', 'timezone']);
        if ($request->hasFile('profile_image')) {
            if ($user->profile_photo_path) Storage::disk('public')->delete($user->profile_photo_path);
            $data['profile_photo_path'] = $request->file('profile_image')->store('profiles/customers', 'public');
        }
        unset($data['profile_image']);
        if ($data['email'] !== $user->email) $data['email_verified_at'] = null;
        if (($data['phone'] ?? null) !== $user->phone) $data['phone_verified_at'] = null;
        $user->update($data);
        AuditLog::record('user.profile_updated', $user, $old, $user->only(array_keys($old)));
        return back()->with('success', 'Profile updated.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);
        $request->user()->update([
            'email_notifications' => $request->boolean('email_notifications'),
            'sms_notifications' => $request->boolean('sms_notifications'),
            'marketing_consent' => $request->boolean('marketing_consent'),
        ]);
        return back()->with('success', 'Notification preferences updated.');
    }
}
