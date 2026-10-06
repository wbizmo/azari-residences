<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Services\Communications\CommunicationRetryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $query = CommunicationLog::query()
            ->where('channel', '!=', 'dispatch')
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('template'), fn ($q) => $q->where('template', 'like', '%'.$request->string('template')->toString().'%'))
            ->latest();

        return view('admin.communications.index', [
            'logs' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function retry(
        CommunicationLog $communicationLog,
        CommunicationRetryService $retry
    ): RedirectResponse {
        $retry->retry($communicationLog);

        return back()->with('success', 'Failed communication queued for a channel-specific retry.');
    }
}
