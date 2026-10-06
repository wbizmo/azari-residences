@extends('admin.layouts.app')
@section('title','Communications')
@section('content')
<section class="az-page-heading">
    <div>
        <span class="eyebrow">Delivery operations</span>
        <h1>Transactional communications</h1>
        <p>Auditable email, SMS, WhatsApp and in-app delivery state. Sensitive provider payloads are not shown.</p>
    </div>
</section>

<form method="GET" class="az-s78-actions" style="margin-bottom:18px">
    <select name="channel">
        <option value="">All channels</option>
        @foreach(['email','sms','whatsapp','in_app'] as $channel)
            <option value="{{ $channel }}" @selected(request('channel')===$channel)>{{ Str::headline($channel) }}</option>
        @endforeach
    </select>
    <select name="status">
        <option value="">All statuses</option>
        @foreach(['queued','sent','delivered','failed','skipped'] as $status)
            <option value="{{ $status }}" @selected(request('status')===$status)>{{ Str::headline($status) }}</option>
        @endforeach
    </select>
    <input name="template" value="{{ request('template') }}" placeholder="Template">
    <button class="az-button" type="submit">Filter</button>
</form>

<div class="az-admin-table-wrap">
<table class="az-admin-table">
    <thead><tr><th>When</th><th>Channel</th><th>Template</th><th>Recipient</th><th>Status</th><th>Provider</th><th>Action</th></tr></thead>
    <tbody>
    @forelse($logs as $log)
        <tr>
            <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
            <td>{{ Str::headline($log->channel) }}</td>
            <td>{{ $log->template }}</td>
            <td>{{ $log->masked_recipient ?: '—' }}</td>
            <td>{{ Str::headline($log->status) }}</td>
            <td>{{ $log->provider ?: '—' }}</td>
            <td>
                @if($log->status==='failed' && in_array($log->channel,['email','sms','whatsapp'],true))
                    <form method="POST" action="{{ route('azari.admin.communications.retry',$log) }}">
                        @csrf
                        <button class="az-button az-button--secondary" type="submit">Retry channel</button>
                    </form>
                @else
                    —
                @endif
            </td>
        </tr>
        @if($log->safe_error)
            <tr><td colspan="7"><small>{{ $log->safe_error }}</small></td></tr>
        @endif
    @empty
        <tr><td colspan="7">No communication logs match these filters.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

{{ $logs->links() }}
@endsection
