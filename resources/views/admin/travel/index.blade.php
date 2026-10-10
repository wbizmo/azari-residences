@extends('admin.layouts.app')
@section('title', 'Travel suppliers')
@section('content')
<div class="admin-content">
    <h1>Travel suppliers and enquiries</h1>
    <p>Phase Four is supplier-gated. Enquiries are not paid bookings, reserved vehicles, issued tickets or airport pickups. Supplier documents require independent verification before approval.</p>

    <section class="az-user-panel" style="padding:20px;margin-bottom:18px">
        <h2>Onboard a supplier</h2>
        <form method="POST" action="{{ route('azari.admin.travel.suppliers.store') }}" class="az-form-grid">
            @csrf
            <label>Product <select name="kind" required><option value="transfer">Ground transfers</option><option value="experience">Experiences</option><option value="car">Car rentals</option><option value="flight">Flights (partner integration required)</option></select></label>
            <label>Legal supplier name <input name="name" maxlength="160" required></label>
            <label>Support email <input type="email" name="support_email" required></label>
            <label>Contract terms URL (HTTPS) <input type="url" name="terms_url" placeholder="https://" required></label>
            <label>Service region <input name="approved_regions[0]" placeholder="e.g. Lagos, NG" required></label>
            <button class="button button-primary" type="submit">Create pending supplier</button>
        </form>
    </section>

    <section class="az-user-panel" style="padding:20px;margin-bottom:18px">
        <h2>Supplier compliance review</h2>
        @forelse($suppliers as $supplier)
            <details style="border-bottom:1px solid #d5dfe7;padding:12px 0">
                <summary><strong>{{ $supplier->name }}</strong> · {{ $supplier->kind }} · {{ $supplier->status }}</summary>
                @if($supplier->status === 'pending')
                    <form method="POST" action="{{ route('azari.admin.travel.suppliers.approve', $supplier) }}" class="az-form-grid">
                        @csrf
                        <label>Contract document reference <input name="contract_reference" required minlength="8" maxlength="160"></label>
                        <label>Operating licence reference <input name="licence_reference" required minlength="8" maxlength="160"></label>
                        <label>Insurance reference <input name="insurance_reference" required minlength="8" maxlength="160"></label>
                        <label>Operating jurisdiction <input name="operating_jurisdiction" required maxlength="120"></label>
                        <label><input type="checkbox" name="review_attestation" value="CONTRACT_AND_SAFETY_VERIFIED" required>
                            I independently verified signed contract, licensing, insurance and applicable consumer safety terms.</label>
                        <button class="button button-primary" type="submit">Approve reviewed supplier</button>
                    </form>
                @else
                    <p>Supplier approval recorded. No API access or customer PII is automatically granted.</p>
                @endif
                @if($supplier->status !== 'paused')
                    <form method="POST" action="{{ route('azari.admin.travel.suppliers.pause', $supplier) }}">
                        @csrf
                        <button class="button" type="submit">Pause supplier (block new requests)</button>
                    </form>
                @endif
            </details>
        @empty
            <p>No supplier partners recorded.</p>
        @endforelse
    </section>

    <section class="az-user-panel" style="padding:20px;margin-bottom:18px">
        <h2>Draft an indicative supplier offer</h2>
        <form method="POST" action="{{ route('azari.admin.travel.offers.store') }}" class="az-form-grid">
            @csrf
            <label>Approved supplier
                <select name="travel_supplier_id" required>
                    <option value="">Choose partner</option>
                    @foreach($suppliers as $supplier)
                        @if($supplier->isApproved())
                            <option value="{{ $supplier->id }}">{{ $supplier->name }} · {{ $supplier->kind }}</option>
                        @endif
                    @endforeach
                </select>
            </label>
            <label>Offer title <input name="title" required maxlength="180" minlength="5"></label>
            <label>Origin or pickup <input name="origin" maxlength="160"></label>
            <label>Destination or meeting point <input name="destination" maxlength="160"></label>
            <label>Local timezone (IANA) <input name="timezone" value="Africa/Lagos" required></label>
            <label>Starts at (UTC) <input type="datetime-local" name="starts_at"></label>
            <label>Offer expires (UTC) <input type="datetime-local" name="expires_at" required></label>
            <label>Maximum party <input type="number" name="max_party" min="1" max="12" value="4" required></label>
            <label>Currency <select name="currency"><option>USD</option><option>EUR</option><option>GBP</option><option>NGN</option><option>CAD</option></select></label>
            <label>Base unit price in minor units <input type="number" name="base_minor" min="0" max="1000000000" required></label>
            <label>Tax per unit (minor) <input type="number" name="tax_minor" min="0" max="1000000000" value="0" required></label>
            <label>Fees per unit (minor) <input type="number" name="fee_minor" min="0" max="1000000000" value="0" required></label>
            <label>Potential refundable deposit (minor) <input type="number" name="deposit_minor" min="0" max="1000000000" value="0"></label>
            <label>What's included <textarea name="terms[included]" maxlength="2500" required></textarea></label>
            <label>Cancellation terms <textarea name="terms[cancellation]" maxlength="2500" required></textarea></label>
            <label>Supplier and consumer disclosure <textarea name="terms[disclosure]" maxlength="2500" required></textarea></label>
            <button class="button button-primary" type="submit">Save draft offer</button>
        </form>
    </section>

    <section class="az-user-panel" style="padding:20px;margin-bottom:18px">
        <h2>Offers and activity time slots</h2>
        @forelse($offers as $offer)
            <details style="border-bottom:1px solid #d5dfe7;padding:12px 0">
                <summary><strong>{{ $offer->title }}</strong> · {{ $offer->kind }} · {{ $offer->supplier?->name }}
                    · {{ $offer->published_at ? 'Published' : 'Draft' }}</summary>
                <p>{{ $offer->currency }} {{ number_format($offer->totalMinor(1) / 100, 2) }} indicative per {{ $offer->price_basis === 'per_vehicle' ? 'vehicle' : 'traveller' }}. Deposit: {{ number_format($offer->deposit_minor / 100, 2) }}.</p>
                @if(!$offer->published_at)
                    <form method="POST" action="{{ route('azari.admin.travel.offers.publish', $offer) }}">
                        @csrf
                        <button class="button button-primary" type="submit">Publish after verification</button>
                    </form>
                @endif
                @if($offer->published_at)
                    <form method="POST" action="{{ route('azari.admin.travel.offers.unpublish', $offer) }}">
                        @csrf
                        <button class="button" type="submit">Unpublish incorrect or unavailable offer</button>
                    </form>
                @endif
                @if($offer->kind === 'experience')
                    <form method="POST" action="{{ route('azari.admin.travel.slots.store', $offer) }}" class="az-form-grid">
                        @csrf
                        <label>Slot start in UTC <input type="datetime-local" name="starts_at" required></label>
                        <label>Capacity <input type="number" name="capacity" min="1" max="100000" required></label>
                        <button class="button button-primary" type="submit">Add finite activity slot</button>
                    </form>
                @endif
            </details>
        @empty
            <p>No travel offers configured.</p>
        @endforelse
    </section>

    @if(config('travel.fulfillment_enabled', false))
    <section class="az-user-panel" style="padding:20px;margin-bottom:18px">
        <h2>Redeem an independently verified experience voucher</h2>
        <p>Only registered voucher codes with confirmed supplier fulfillment and verified payment may be redeemed. Each code works once.</p>
        <form method="POST" action="{{ route('azari.admin.travel.vouchers.redeem') }}" class="az-form-grid">
            @csrf
            <label>Approved supplier <select name="supplier_id" required>
                @foreach($suppliers as $supplier)
                    @if($supplier->isApproved() && $supplier->kind === 'experience')
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endif
                @endforeach
            </select></label>
            <label>Voucher code <input name="voucher_code" required maxlength="48" autocomplete="off"></label>
            <button class="button button-primary" type="submit">Validate and redeem once</button>
        </form>
    </section>
    @endif
    <section class="az-user-panel" style="padding:20px">
        <h2>Outstanding supplier enquiries</h2>
        <p>Recording acknowledgement never confirms a flight, vehicle, activity ticket or travel payment.</p>
        @forelse($pendingRequests as $travel)
            <details style="border-bottom:1px solid #d5dfe7;padding:12px 0">
                <summary>{{ ucfirst($travel->kind) }} · {{ $travel->offer?->title }} · {{ $travel->status }} · expires {{ $travel->expires_at->format('j M Y H:i') }} UTC</summary>
                <form method="POST" action="{{ route('azari.admin.travel.requests.review', $travel) }}" class="az-form-grid">
                    @csrf
                    <label>Supplier decision <select name="decision"><option value="decline">Decline</option><option value="acknowledge">Acknowledge (requires consent and real supplier reference)</option></select></label>
                    <label>Supplier acknowledgement reference <input name="supplier_reference" maxlength="160"></label>
                    <button class="button button-primary" type="submit">Record review</button>
                </form>
            </details>
        @empty
            <p>No outstanding enquiries.</p>
        @endforelse
    </section>
</div>
@endsection
