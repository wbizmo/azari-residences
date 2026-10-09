@extends('admin.layouts.app')
@section('title','Payment '.$payment->reference)
@section('content')
<section class="az-page-heading"><div><p class="az-eyebrow">Payment record</p><h1>{{ $payment->reference }}</h1><p>Safe operational summary. Provider secrets and raw sensitive payloads are never displayed.</p></div><div class="az-s78-actions"><a class="az-button az-button--secondary" href="{{ route('azari.admin.payments.index') }}">Back to payments</a>@if($payment->proof_path)<a class="az-button az-button--secondary" href="{{ route('azari.admin.payments.proof',$payment) }}">Download proof</a>@endif @if($payment->provider!=='manual' && !in_array($payment->status,['successful','successful_excess'],true) && $payment->provider_reference)<form method="POST" action="{{ route('azari.admin.payments.reconcile',$payment) }}">@csrf<button class="az-button" type="submit">Reconcile now</button></form>@endif</div></section>
<div class="az-s78-grid"><section class="az-s78-card"><h2>Payment summary</h2><dl class="az-s78-detail"><div><dt>Status</dt><dd><span class="az-s78-badge {{ in_array($payment->status,['failed','invalid'],true)?'is-danger':($payment->status!=='successful'?'is-warning':'') }}">{{ $payment->status }}</span></dd></div><div><dt>Booking</dt><dd><a href="{{ route('azari.admin.bookings.show',$payment->booking) }}">{{ $payment->booking?->reference }}</a></dd></div><div><dt>User</dt><dd>{{ $payment->user?->name ?? $payment->booking?->guest_name }}</dd></div><div><dt>Provider</dt><dd>{{ ucfirst($payment->provider) }}</dd></div><div><dt>Provider reference</dt><dd>{{ $payment->provider_reference ?: 'Pending' }}</dd></div><div><dt>Amount</dt><dd>{{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</dd></div><div><dt>Method</dt><dd>{{ $payment->payment_method ?: 'Not reported' }}</dd></div><div><dt>Initiated</dt><dd>{{ $payment->initiated_at?->format('d M Y H:i') ?: '—' }}</dd></div><div><dt>Paid</dt><dd>{{ $payment->paid_at?->format('d M Y H:i') ?: '—' }}</dd></div><div><dt>Verified</dt><dd>{{ $payment->verified_at?->format('d M Y H:i') ?: '—' }}</dd></div><div><dt>Receipt association</dt><dd>{{ $payment->receipt_number ?: 'Not available' }}</dd></div><div><dt>Created by</dt><dd>{{ $payment->createdBy?->name ?? ucfirst($payment->creation_source) }}</dd></div></dl>@if($payment->administrative_note)<h3>Administrative note</h3><p>{{ $payment->administrative_note }}</p>@endif</section>
<section class="az-s78-card"><h2>Verification attempts</h2>@forelse($verificationAttempts as $attempt)<article class="az-s78-event"><strong>{{ Str::headline($attempt->result) }}</strong><p>{{ $attempt->attempted_at?->format('d M Y H:i:s') }} · Status {{ $attempt->provider_status ?: 'not reported' }} · Amount {{ $attempt->reported_currency }} {{ $attempt->reported_amount }}</p>@if($attempt->safe_error)<p>{{ $attempt->safe_error }}</p>@endif</article>@empty<div class="az-s78-empty">No verification attempts recorded.</div>@endforelse{{ $verificationAttempts->links() }}</section></div>
<div class="az-s78-grid" style="margin-top:18px">
<section class="az-s78-card">
<h2>Refunds</h2>
@if($payment->isSuccessful() && $payment->refundableBalance() > 0)
<form method="POST" action="{{ route('azari.admin.payments.refunds.store',$payment) }}" class="az-form-grid">
@csrf
<label><span>Refund amount</span><input type="number" name="amount" min="0.01" max="{{ $payment->refundableBalance() }}" step="0.01" required></label>
<label class="wide"><span>Reason</span><textarea name="reason" maxlength="500" required></textarea></label>
<button class="az-button" type="submit">Create refund request</button>
</form>
<p>Refundable balance: {{ $payment->currency }} {{ number_format($payment->refundableBalance(),2) }}</p>
@endif
@forelse($refunds as $refund)
<article class="az-s78-event">
<strong>{{ $refund->reference }} · {{ Str::headline($refund->status) }}</strong>
<p>{{ $refund->currency }} {{ number_format((float)$refund->amount,2) }} · {{ $refund->reason }}</p>
@if($refund->provider_reference)<p>Provider reference: {{ $refund->provider_reference }}</p>@endif
@if($refund->safe_error)<p>{{ $refund->safe_error }}</p>@endif
@if($refund->status!=='successful')
<form method="POST" action="{{ route('azari.admin.payments.refunds.update',[$payment,$refund]) }}" class="az-s78-actions">
@csrf
@method('PATCH')
<select name="action" required>
<option value="processing">Mark processing</option>
@if($payment->provider === 'manual' && auth()->user()?->isAdministrator())
<option value="successful">Successful (verified manual payment only)</option>
@endif
<option value="failed">Mark failed</option>
</select>
<input name="provider_reference" maxlength="190" placeholder="Provider refund reference">
<input name="safe_error" maxlength="500" placeholder="Failure note if applicable">
<button class="az-button az-button--secondary" type="submit">Update refund</button>
</form>
@endif
@if($payment->provider === 'flutterwave' && auth()->user()?->isAdministrator())
    @if($refund->status === 'requested')
        <form method="POST" action="{{ route('azari.admin.payments.refunds.dispatch', [$payment, $refund]) }}" class="az-s78-actions">
            @csrf
            <button class="az-button" type="submit">Submit Flutterwave refund</button>
        </form>
        <p>Submitting does not mean the customer has received the refund.</p>
    @elseif(in_array($refund->status, ['processing', 'reconciliation_required'], true) && $refund->provider_reference)
        <form method="POST" action="{{ route('azari.admin.payments.refunds.reconcile', [$payment, $refund]) }}" class="az-s78-actions">
            @csrf
            <button class="az-button az-button--secondary" type="submit">Verify provider refund settlement</button>
        </form>
    @endif
@endif
</article>
@empty
<div class="az-s78-empty">No refunds recorded.</div>
@endforelse
{{ $refunds->links() }}
</section>
<section class="az-s78-card"><h2>Callback and webhook events</h2>@forelse($events as $event)<article class="az-s78-event"><strong>{{ ucfirst($event->source) }} · {{ $event->event_type ?: 'payment event' }}</strong><p>{{ $event->received_at?->format('d M Y H:i:s') }} · Signature {{ $event->signature_valid===false?'invalid':'accepted' }} · {{ $event->processed?'processed':'not processed' }}</p>@if($event->safe_error)<p>{{ $event->safe_error }}</p>@endif</article>@empty<div class="az-s78-empty">No provider events recorded.</div>@endforelse{{ $events->links() }}</section><section class="az-s78-card"><h2>Audit history</h2>@forelse($auditLogs as $log)<article class="az-s78-event"><strong>{{ Str::headline($log->action) }}</strong><p>{{ $log->created_at?->format('d M Y H:i:s') }} · {{ $log->actor?->name ?? 'System' }}</p></article>@empty<div class="az-s78-empty">No audit events recorded.</div>@endforelse{{ $auditLogs->links() }}<h2 style="margin-top:22px">Receipt</h2>@if(in_array($payment->status,['successful','successful_excess'],true))<p><a class="az-button" href="{{ route('azari.admin.bookings.receipt',$payment->booking) }}" target="_blank">Print or save receipt</a></p>@else<p>No receipt is available for this payment.</p>@endif</section></div>
<section class="az-s78-card" style="margin-top:18px">
<h2>Provider disputes and owner payout holds</h2>
<p>A newly reported chargeback holds the affected owner funds. Settlement loss requires independent administrator review and external evidence.</p>
@if(auth()->user()?->isAdministrator() && $payment->isSuccessful())
<form method="POST" class="az-form-grid" action="{{ route('azari.admin.payments.disputes.store', $payment) }}">
@csrf
<label><span>Provider dispute reference</span><input name="provider_dispute_reference" maxlength="190" required minlength="5"></label>
<label><span>Evidence or case reference</span><input name="evidence_reference" maxlength="190" required minlength="5"></label>
<label><span>Disputed amount ({{ $payment->currency }})</span><input type="number" name="amount" min="0.01" max="{{ $payment->amount }}" step="0.01" required></label>
<button class="az-button" type="submit">Record dispute and hold funds</button>
</form>
@endif
@forelse($payment->disputes()->orderByDesc('id')->get() as $dispute)
<article class="az-s78-event">
<strong>{{ $dispute->provider_dispute_reference }} · {{ Str::headline($dispute->status) }}</strong>
<p>{{ $dispute->currency }} {{ number_format((float) $dispute->amount, 2) }} · Evidence: {{ $dispute->evidence_reference }}</p>
@if($dispute->status === 'open' && auth()->user()?->isAdministrator() && (int) $dispute->reported_by !== (int) auth()->id())
<form method="POST" class="az-form-grid" action="{{ route('azari.admin.payments.disputes.resolve', $dispute) }}">
@csrf
<label><span>Verified provider outcome</span><select name="decision" required><option value="won">Won - release hold</option><option value="lost">Lost - debit owner ledger</option></select></label>
<label><span>Provider settlement evidence</span><input name="evidence_reference" maxlength="190" required minlength="5"></label>
<label class="wide"><span>Independent reviewer note</span><textarea name="review_note" required minlength="8" maxlength="2000"></textarea></label>
<button class="az-button az-button--secondary" type="submit">Record reviewed outcome</button>
</form>
@endif
</article>
@empty
<p>No provider disputes recorded for this payment.</p>
@endforelse
</section>
@endsection
