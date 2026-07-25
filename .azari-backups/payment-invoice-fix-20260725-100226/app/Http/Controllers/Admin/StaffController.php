<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\StaffLoginHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $staff = User::query()->where(fn ($q) => $q->whereNotNull('staff_role')->orWhere('is_admin', true))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($i) => $i->where('name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%')->orWhere('username', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(10)->withQueryString();
        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View { return view('admin.staff.form', ['staffMember' => new User, 'permissions' => $this->permissions()]); }

    public function edit(User $user): View
    {
        abort_unless($user->isStaff(), 404);
        return view('admin.staff.form', ['staffMember' => $user->load('directPermissions'), 'permissions' => $this->permissions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $photo = $request->hasFile('profile_photo') ? $request->file('profile_photo')->store('profiles/staff', 'public') : null;
        $staff = User::query()->create([
            'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'staff_role' => $data['staff_role'], 'account_type' => 'staff', 'status' => 'active', 'is_active' => true,
            'profile_photo_path' => $photo, 'password' => Hash::make($data['password']), 'email_verified_at' => now(),
        ]);
        $this->syncPermissions($staff, $data['permissions'] ?? [], $request->user()->id);
        AuditLog::record('staff.created', $staff, [], ['email' => $staff->email, 'staff_role' => $staff->staff_role, 'permissions' => $data['permissions'] ?? []]);
        return redirect()->route('azari.admin.staff.index')->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        $data = $this->validated($request, $user);
        $old = $user->only(['name', 'username', 'email', 'phone', 'staff_role', 'is_active']);
        $updates = collect($data)->only(['name', 'username', 'email', 'phone', 'staff_role'])->all();
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) Storage::disk('public')->delete($user->profile_photo_path);
            $updates['profile_photo_path'] = $request->file('profile_photo')->store('profiles/staff', 'public');
        }
        $user->update($updates);
        $this->syncPermissions($user, $data['permissions'] ?? [], $request->user()->id);
        AuditLog::record('staff.updated', $user, $old, $updates, ['permissions' => $data['permissions'] ?? []]);
        return redirect()->route('azari.admin.staff.index')->with('success', 'Staff account updated.');
    }

    public function replacePassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed']]);
        $user->update(['password' => Hash::make($data['password'])]);
        AuditLog::record('staff.password_replaced', $user);
        return back()->with('success', 'Staff password replaced.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend your own account.');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $user->update(['is_active' => false, 'status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => $data['reason'] ?? 'Suspended by administrator']);
        AuditLog::record('staff.suspended', $user, [], ['reason' => $data['reason'] ?? null]);
        return back()->with('success', 'Staff account suspended.');
    }

    public function reactivate(User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        $user->update(['is_active' => true, 'status' => 'active', 'suspended_at' => null, 'suspension_reason' => null]);
        AuditLog::record('staff.reactivated', $user);
        return back()->with('success', 'Staff account reactivated.');
    }

    public function activity(User $user): View
    {
        abort_unless($user->isStaff(), 404);
        return view('admin.staff.activity', [
            'staffMember' => $user,
            'logins' => StaffLoginHistory::query()->where('user_id', $user->id)->latest('logged_in_at')->paginate(10, ['*'], 'logins'),
            'activity' => AuditLog::query()->where('actor_id', $user->id)->latest()->paginate(10, ['*'], 'activity'),
        ]);
    }

    public function toggle(User $user): RedirectResponse
    {
        return $user->is_active ? $this->suspend(request(), $user) : $this->reactivate($user);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'alpha_dash', 'max:80', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'staff_role' => ['required', Rule::in(['administrator', 'support', 'staff'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'confirmed'],
            'profile_photo' => ['nullable', 'image', 'max:4096'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);
    }

    private function permissions(): array
    {
        return Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group')->all();
    }

    private function syncPermissions(User $staff, array $slugs, int $grantedBy): void
    {
        if ($staff->isAdministrator()) {
            $staff->directPermissions()->detach();
            return;
        }
        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id');
        $sync = $ids->mapWithKeys(fn ($id) => [$id => ['granted_by' => $grantedBy]])->all();
        $before = $staff->directPermissions()->pluck('slug')->all();
        $staff->directPermissions()->sync($sync);
        AuditLog::record('staff.permissions_changed', $staff, ['permissions' => $before], ['permissions' => array_values($slugs)], actorId: $grantedBy);
    }
}
