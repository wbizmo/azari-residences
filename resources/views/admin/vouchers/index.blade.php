@extends('admin.layouts.app')
@section('content')
<div class="az-admin-page-header"><div><span class="az-eyebrow">Revenue tools</span><h1>Vouchers</h1><p>Create booking discount codes and review usage.</p></div></div>
@if(session('success'))<div class="az-admin-alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="az-admin-alert az-admin-alert--danger">{{ $errors->first() }}</div>@endif
<section class="az-admin-card"><h2>Create voucher</h2><form method="POST" action="{{ route('azari.admin.vouchers.store') }}" class="az-admin-grid">@csrf
<label>Code<input name="code" value="{{ old('code') }}" required></label><label>Name<input name="name" value="{{ old('name') }}" required></label>
<label>Type<select name="discount_type"><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select></label><label>Value<input type="number" step="0.01" min="0.01" name="discount_value" required></label>
<label>Maximum discount<input type="number" step="0.01" name="maximum_discount"></label><label>Minimum booking value<input type="number" step="0.01" name="minimum_booking_value" value="0"></label>
<label>Starts at<input type="datetime-local" name="starts_at"></label><label>Expires at<input type="datetime-local" name="expires_at"></label>
<label>Total limit<input type="number" min="1" name="total_usage_limit"></label><label>Per-customer limit<input type="number" min="1" name="per_customer_limit" value="1" required></label>
<label>Status<select name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></label><label>Properties<select name="property_ids[]" multiple>@foreach($properties as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select><small>Leave empty for every property.</small></label>
<button class="az-admin-button" type="submit">Create voucher</button></form></section>
<section class="az-admin-card"><div class="az-admin-table-wrap"><table class="az-admin-table"><thead><tr><th>Code</th><th>Name</th><th>Discount</th><th>Uses</th><th>Window</th><th>Status</th></tr></thead><tbody>@forelse($vouchers as $voucher)<tr><td><strong>{{ $voucher->code }}</strong></td><td>{{ $voucher->name }}</td><td>{{ $voucher->discount_type==='percentage' ? rtrim(rtrim(number_format($voucher->discount_value,2),'0'),'.').'%' : number_format($voucher->discount_value,2) }}</td><td>{{ $voucher->redemptions_count }} / {{ $voucher->total_usage_limit ?? 'Unlimited' }}</td><td>{{ $voucher->starts_at?->format('d M Y') ?? 'Now' }} – {{ $voucher->expires_at?->format('d M Y') ?? 'No expiry' }}</td><td>{{ $voucher->is_active?'Active':'Inactive' }}</td></tr>@empty<tr><td colspan="6">No vouchers created.</td></tr>@endforelse</tbody></table></div>{{ $vouchers->links() }}</section>
@endsection
