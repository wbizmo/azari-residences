<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Services\Identity\DojahService;
use App\Models\IdentityVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DojahVerificationController extends Controller
{
    public function user(Request $request, DojahService $dojah): View
    {
        $user = $request->user();
        $latest = $dojah->latestForUser($user);
        $verification = $latest?->isVerified()
            ? $latest
            : $dojah->verificationForUser($user);

        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        $continueUrl = $verification->isVerified()
            ? $request->session()->pull('url.intended', route('user.dashboard'))
            : $request->session()->get('url.intended', route('user.dashboard'));

        return view('user.identity.dojah', [
            'verification' => $verification,
            'widgetConfigured' => $dojah->widgetConfigured(),
            'widget' => $dojah->widgetPayload($verification, [
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
                'email' => $user->email,
            ]),
            'subjectName' => $user->name,
            'backUrl' => route('user.dashboard'),
            'continueUrl' => $continueUrl,
        ]);
    }

    public function status(Request $request, DojahService $dojah): JsonResponse
    {
        $latest = $dojah->latestForUser($request->user());

        return response()->json([
            'verified' => $latest?->isVerified() ?? false,
            'status' => $latest?->status ?? IdentityVerification::STATUS_PENDING,
            'failure_reason' => $latest?->failure_reason,
            'verified_at' => $latest?->verified_at?->toIso8601String(),
            'continue_url' => $request->session()->get('url.intended', route('user.dashboard')),
        ])->header('Cache-Control', 'no-store, private');
    }

}
