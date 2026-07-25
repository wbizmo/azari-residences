<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => User::query()->whereNotNull('staff_role')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180', 'unique:users,email'],
            'staff_role' => ['required', Rule::in(['administrator', 'support'])],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'profile_photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $photo = $request->hasFile('profile_photo')
            ? $request->file('profile_photo')->store('profiles/staff', 'public')
            : null;

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'staff_role' => $data['staff_role'],
            'is_active' => true,
            'profile_photo_path' => $photo,
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('azari.admin.staff.index')->with('status', 'Staff account created.');
    }

    public function toggle(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot deactivate your own account.');
        abort_unless($user->staff_role, 403);

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', 'Staff status updated.');
    }
}
