<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class UserSecurityController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = collect();
        $table = (string) config('session.table', 'sessions');
        if (config('session.driver') === 'database' && Schema::hasTable($table)) {
            $sessions = DB::table($table)
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->get();
        }
        return view('user.security.index', compact('sessions'));
    }

    public function destroySession(Request $request, string $session): RedirectResponse
    {
        $table = (string) config('session.table', 'sessions');
        abort_unless(config('session.driver') === 'database' && Schema::hasTable($table), 404);
        abort_if($session === $request->session()->getId(), 422, 'Use logout to end the current session.');
        DB::table($table)->where('user_id', $request->user()->id)->where('id', $session)->delete();
        return back()->with('success', 'Session ended.');
    }
}
