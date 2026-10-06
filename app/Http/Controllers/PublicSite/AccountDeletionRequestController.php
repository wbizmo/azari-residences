<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Mail\AccountDeletionRequestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AccountDeletionRequestController extends Controller
{
    public function show(Request $request): View
    {
        return view('public.account-deletion', [
            'accountEmail' => $request->user()?->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'confirm' => ['accepted'],
            'company_website' => ['nullable', 'max:0'],
        ]);

        $recipient = collect([
            config('azari.contact_recipient_email'),
            config('azari.support_ticket_email'),
            config('mail.from.address'),
        ])->first(fn ($value): bool => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false);

        if (! $recipient) {
            Log::warning('Account deletion request could not be delivered because no recipient email is configured.');

            return back()
                ->withInput($request->except(['confirm', 'company_website']))
                ->withErrors(['email' => 'Deletion requests are temporarily unavailable. Please contact Resarva support and try again later.']);
        }

        $reference = 'AZDEL-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));

        try {
            Mail::to($recipient)->send(new AccountDeletionRequestMail(
                reference: $reference,
                requesterName: trim((string) ($data['name'] ?? '')),
                requesterEmail: $data['email'],
                requestedAt: now()->toIso8601String(),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['confirm', 'company_website']))
                ->withErrors(['email' => 'We could not submit your deletion request right now. Please try again later.']);
        }

        return redirect()
            ->route('account-deletion.show')
            ->with('account_deletion_reference', $reference);
    }
}
