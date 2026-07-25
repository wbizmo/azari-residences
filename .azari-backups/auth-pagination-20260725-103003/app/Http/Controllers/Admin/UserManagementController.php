<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->whereNull('staff_role')->where('is_admin', false)
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(config('azari.pagination.per_page', 10))->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    public function create(): View { return view('admin.users.edit', ['user' => new User]); }
    public function edit(User $user): View { abort_if($user->isStaff(), 404); return view('admin.users.edit', compact('user')); }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);
        $data += ['account_type' => 'customer', 'status' => 'active', 'is_active' => true, 'email_verified_at' => now()];
        User::create($data);
        return redirect()->route('azari.admin.users.index')->with('success', 'Customer created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isStaff(), 404);
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) unset($data['password']); else $data['password'] = Hash::make($data['password']);
        $user->update($data);
        return redirect()->route('azari.admin.users.index')->with('success', 'Customer updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->isStaff(), 404);
        $user->delete();
        return back()->with('success', 'Customer deleted.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isStaff(), 404);
        $user->update(['status' => 'suspended', 'is_active' => false, 'suspended_at' => now(), 'suspension_reason' => $request->input('reason', 'Suspended by administrator')]);
        return back()->with('success', 'Customer suspended.');
    }

    public function reactivate(User $user): RedirectResponse
    {
        abort_if($user->isStaff(), 404);
        $user->update(['status' => 'active', 'is_active' => true, 'suspended_at' => null, 'suspension_reason' => null]);
        return back()->with('success', 'Customer reactivated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
    }
}
