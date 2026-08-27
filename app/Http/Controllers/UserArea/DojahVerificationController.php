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

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'verified' => IdentityVerification::userIsVerified((int) $request->user()->id),
            'continue_url' => $request->session()->get('url.intended', route('user.dashboard')),
        ]);
    }

}
