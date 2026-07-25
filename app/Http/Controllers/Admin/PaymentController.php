<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use App\Models\PaymentProviderStatus;
use App\Services\Payments\PaymentFinalizer;
use App\Services\Payments\PaymentProviderHealthService;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Payment::query()->with(['booking.property', 'user']);
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', strtoupper($request->string('currency')->toString())))
            ->when($request->filled('booking'), fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('reference', 'like', '%'.$request->string('booking').'%')))
            ->when($request->filled('user'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->string('user').'%')->orWhere('email', 'like', '%'.$request->string('user').'%')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('amount_min'), fn ($q) => $q->where('amount', '>=', $request->input('amount_min')))
            ->when($request->filled('amount_max'), fn ($q) => $q->where('amount', '<=', $request->input('amount_max')));
        return view('admin.payments.index', ['payments' => $query->latest()->paginate(10)->withQueryString()]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['booking.property', 'booking.user', 'user', 'createdBy']);
        $verificationAttempts = $payment->verificationAttempts()->paginate(10, ['*'], 'verifications')->withQueryString();
        $events = $payment->events()->paginate(10, ['*'], 'events')->withQueryString();
        $auditLogs = AuditLog::query()
            ->with('actor')
            ->where('subject_type', $payment->getMorphClass())
            ->where('subject_id', $payment->getKey())
            ->latest()
            ->paginate(10, ['*'], 'audit')
            ->withQueryString();

        return view('admin.payments.show', compact('payment', 'verificationAttempts', 'events', 'auditLogs'));
    }

    public function create(Request $request): View
    {
        $bookings = Booking::query()->with('property')->whereNotIn('status', ['cancelled', 'completed', 'checked_out'])->latest()->paginate(10);
        $selected = $request->filled('booking') ? Booking::query()->find($request->integer('booking')) : null;
        return view('admin.payments.create', compact('bookings', 'selected'));
    }

    public function store(Request $request, PaymentFinalizer $finalizer): RedirectResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method' => ['required', 'string', 'max:80'],
            'provider' => ['required', Rule::in(['manual', 'flutterwave', 'pesapal', 'intouch'])],
            'external_reference' => ['required', 'string', 'max:190'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:3000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'confirm_over_allocation' => ['nullable', 'accepted'],
        ]);
        $booking = Booking::query()->findOrFail($data['booking_id']);
        if (strtoupper($data['currency']) !== strtoupper($booking->currency)) {
            throw ValidationException::withMessages(['currency' => 'The payment currency must match the booking currency.']);
        }
        $remaining = $booking->balanceDue();
        if ((float) $data['amount'] > $remaining && ! $request->boolean('confirm_over_allocation')) {
            throw ValidationException::withMessages(['amount' => 'This amount exceeds the booking balance. Confirm the over-allocation to continue.']);
        }
        if (Payment::query()->where('provider', $data['provider'])->where('provider_reference', $data['external_reference'])->exists()) {
            throw ValidationException::withMessages(['external_reference' => 'This external reference is already linked.']);
        }

        $proof = $request->hasFile('proof') ? $request->file('proof')->store('payment-proofs', 'private') : null;
        $payment = Payment::query()->create([
            'reference' => 'PAY-MAN-'.now()->format('ymdHis').'-'.Str::upper(Str::random(6)),
            'provider_reference' => $data['external_reference'],
            'provider' => $data['provider'],
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'guest_email' => $booking->guest_email,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency']),
            'status' => 'pending',
            'payment_method' => $data['payment_method'],
            'initiated_at' => now(),
            'created_by' => $request->user()->id,
            'creation_source' => 'administrator',
            'proof_disk' => $proof ? 'private' : null,
            'proof_path' => $proof,
            'administrative_note' => $data['note'] ?? null,
        ]);
        $finalizer->apply($payment, [
            'status' => 'successful',
            'provider_status' => 'MANUALLY_RECORDED',
            'provider_reference' => $data['external_reference'],
            'merchant_reference' => $payment->reference,
            'amount' => (float) $data['amount'],
            'currency' => strtoupper($data['currency']),
            'payment_method' => $data['payment_method'],
            'paid_at' => $data['paid_at'],
            'safe_response' => ['recorded_by' => $request->user()->id, 'paid_at' => $data['paid_at']],
        ], 'administrator');
        AuditLog::record('payment.manually_linked', $payment, [], ['booking_reference' => $booking->reference, 'amount' => $data['amount']], actorId: $request->user()->id);
        return redirect()->route('azari.admin.payments.show', $payment)->with('success', 'External payment linked and audited.');
    }


    public function reconcile(Payment $payment, PaymentReconciliationService $reconciliation): RedirectResponse
    {
        try {
            $result = $reconciliation->reconcile($payment, 'administrator_reconciliation');
            return back()->with($result->isSuccessful() ? 'success' : 'warning', $result->isSuccessful()
                ? 'Provider reconciliation verified this payment successfully.'
                : 'Provider reconciliation completed. Current status: '.str_replace('_', ' ', $result->status).'.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Provider reconciliation could not be completed. The failure was logged safely.');
        }
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk($payment->proof_disk)->exists($payment->proof_path), 404);
        return Storage::disk($payment->proof_disk)->download($payment->proof_path, 'payment-proof-'.$payment->reference);
    }

    public function providers(PaymentProviderHealthService $health): View
    {
        $health->refresh();
        return view('admin.payments.providers', ['providers' => PaymentProviderStatus::query()->orderBy('provider')->get()]);
    }

    public function testProvider(string $provider, PaymentProviderHealthService $health): RedirectResponse
    {
        abort_unless(in_array($provider, ['flutterwave', 'pesapal', 'intouch'], true), 404);
        $status = $health->refresh($provider)[$provider];
        return back()->with($status->connection_status === 'successful' ? 'success' : 'error', $status->safe_message ?: 'Provider check completed.');
    }
}
