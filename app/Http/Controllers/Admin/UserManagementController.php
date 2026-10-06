<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->whereNull('staff_role')->where('is_admin', false)
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')
                ->orWhere('phone', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(10)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        abort_if($user->isStaff(), 404);
        $user->loadCount(['bookings', 'payments']);
        $bookings = $user->bookings()->with('property')->latest()->paginate(10, ['*'], 'bookings')->withQueryString();
        $payments = $user->payments()->with('booking')->latest()->paginate(10, ['*'], 'payments')->withQueryString();

        return view('admin.users.show', compact('user', 'bookings', 'payments'));
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
}
