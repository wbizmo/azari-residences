@extends('layouts.user')
@section('title','Commercial setup')
@section('kicker','Property Centre')
@section('page_title',$property->name)
@section('content')
<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Commercial setup</h2>
            <p class="az-user-panel-subtitle">Manage unit quantities, accommodation types, rates and booking policies for this approved property.</p>
        </div>
    </header>
</section>

@include('partials.commercial-inventory-manager', ['property' => $property, 'roomTypes' => $roomTypes, 'ownerMode' => true])

@include('user.owner.partials.inventory-calendar', ['property' => $property, 'board' => $inventoryBoard])

@foreach($property->accommodationTypes as $type)
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">{{ $type->name }} calendar</h2>
            <p class="az-user-panel-subtitle">Bulk-edit availability, restrictions and price overrides for up to 367 days. Every change is audited.</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <p>Owner-controlled, evidence-based pricing. Preview first, then approve the exact recommended nightly override. Existing confirmed booking totals stay unchanged.</p>
        <form method="POST" action="{{ route('user.owner.commercial.yield-approve', [$property,$type]) }}"
              data-yield-form data-preview-url="{{ route('user.owner.commercial.yield-preview', [$property,$type]) }}"
              class="az-form-grid">
            @csrf
            <label><span>Start date</span><input type="date" name="from_date" value="{{ now()->addDays(10)->toDateString() }}" required></label>
            <label><span>End date</span><input type="date" name="to_date" value="{{ now()->addDays(17)->toDateString() }}" required></label>
            <label><span>Minimum permitted rate (optional)</span><input type="number" name="minimum" min="0" step="1"></label>
            <label><span>Maximum permitted rate (optional)</span><input type="number" name="maximum" min="0" step="1"></label>
            <label><span>Recommended {{ $type->currency }} nightly rate</span><input type="text" name="accepted_rate" readonly required></label>
            <input type="hidden" name="expected_revision" value="">
            <button type="button" class="az-user-button az-user-button--outline" data-yield-preview>Preview evidence and recommendation</button>
            <button type="submit" class="az-user-button az-user-button--dark" data-yield-apply disabled>Approve exact recommended rate</button>
            <p class="wide" role="status" aria-live="polite" data-yield-feedback>Recommendations require sufficient confirmed bookings, open inventory, and an explicit owner approval.</p>
        </form>
        <form method="POST" action="{{ route('user.owner.commercial.calendar.bulk-update',[$property,$type]) }}" data-inventory-calendar-form data-preview-url="{{ route('user.owner.phase2.calendar.preview', [$property, $type]) }}" class="az-form-grid">
            @csrf
            <label><span>From</span><input type="date" name="from_date" required></label>
            <label><span>To</span><input type="date" name="to_date" required></label>
            <label><span>Sellable quantity</span><input type="number" name="sellable_inventory" min="0" max="{{ $type->total_inventory }}"></label>
            <label><span>Maintenance quantity</span><input type="number" name="maintenance_inventory" min="0" max="{{ $type->total_inventory }}"></label>
            <label><span>Minimum stay</span><input type="number" name="minimum_stay" min="1" max="730"></label>
            <label><span>Maximum stay</span><input type="number" name="maximum_stay" min="1" max="730"></label>
            <label><span>Nightly override</span><input type="number" name="price_override" min="0" step="0.01"></label>
            <label><span>Stop sell</span>
                <select name="stop_sell">
                    <option value="">Keep existing setting</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </label>
            <label><span>Closed to arrival</span>
                <select name="closed_to_arrival">
                    <option value="">Keep existing setting</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </label>
            <label><span>Closed to departure</span>
                <select name="closed_to_departure">
                    <option value="">Keep existing setting</option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </label>
            <input type="hidden" name="expected_revision" value="">
            <button class="az-user-button az-user-button--outline" type="button" data-inventory-preview>Preview proposed changes</button>
            <button class="az-user-button az-user-button--dark" type="submit" data-inventory-apply disabled>Apply reviewed calendar update</button>
            <p class="wide" data-inventory-feedback role="status" aria-live="polite">
                Preview this date range before applying. Any edit requires another preview.
            </p>
        </form>
    </div>
</section>
@endforeach
<script>
(() => {
    'use strict';
    document.querySelectorAll('[data-yield-form]').forEach(form => {
        const preview = form.querySelector('[data-yield-preview]');
        const submit = form.querySelector('[data-yield-apply]');
        const revision = form.querySelector('[name="expected_revision"]');
        const rate = form.querySelector('[name="accepted_rate"]');
        const feedback = form.querySelector('[data-yield-feedback]');
        let currentRequest = null;
        let sequence = 0;
        const invalidate = () => {
            sequence++;
            currentRequest?.abort();
            revision.value = '';
            rate.value = '';
            submit.disabled = true;
            feedback.textContent = 'Re-preview the latest inventory and confirmed booking evidence before approval.';
        };
        form.addEventListener('input', event => { if (event.target !== rate && event.target !== revision) invalidate(); });
        form.addEventListener('change', event => { if (event.target !== rate && event.target !== revision) invalidate(); });
        form.addEventListener('submit', event => {
            if (!/^[a-f0-9]{64}$/.test(revision.value) || !rate.value) event.preventDefault();
        });
        preview.addEventListener('click', async () => {
            invalidate();
            const ticket = ++sequence;
            currentRequest = new AbortController();
            preview.disabled = true;
            feedback.textContent = 'Checking confirmed room nights and daily restrictions…';
            try {
                // Never place CSRF tokens, accepted amounts or revision hashes
                // into a GET query string (browser history and access logs).
                const query = new URLSearchParams();
                ['from_date', 'to_date', 'minimum', 'maximum'].forEach(field => {
                    const value = form.elements.namedItem(field)?.value;
                    if (value) query.set(field, value);
                });
                const response = await fetch(form.dataset.previewUrl + '?' + query, {
                    credentials: 'same-origin', headers: {'Accept': 'application/json'},
                    signal: currentRequest.signal
                });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Pricing preview failed.');
                if (sequence !== ticket) return;
                feedback.textContent = data.reason + ' Booked room nights: ' + data.booked_room_nights +
                    '; capacity room nights: ' + data.capacity_room_nights +
                    '; confirmed bookings sampled: ' + data.sample_bookings + '.';
                if (data.eligible && /^[a-f0-9]{64}$/.test(data.revision || '') && data.suggested_rate !== null) {
                    revision.value = data.revision;
                    rate.value = data.suggested_rate;
                    submit.disabled = false;
                }
            } catch (error) {
                if (sequence === ticket && error.name !== 'AbortError') feedback.textContent = error.message;
            } finally { if (sequence === ticket) preview.disabled = false; }
        });
    });
})();
</script>
<script>
(() => {
    'use strict';
    document.querySelectorAll('[data-inventory-calendar-form]').forEach(form => {
        const revision = form.querySelector('[name="expected_revision"]');
        const preview = form.querySelector('[data-inventory-preview]');
        const apply = form.querySelector('[data-inventory-apply]');
        const feedback = form.querySelector('[data-inventory-feedback]');
        let requestController = null;
        let requestSequence = 0;

        const invalidate = () => {
            requestSequence++;
            requestController?.abort();
            preview.disabled = false;
            revision.value = '';
            apply.disabled = true;
            feedback.textContent = 'Changes have not been previewed. Review the proposed dates and inventory before applying.';
        };
        form.addEventListener('input', event => {
            if (event.target !== revision) invalidate();
        });
        form.addEventListener('change', event => {
            if (event.target !== revision) invalidate();
        });
        form.addEventListener('submit', event => {
            if (!/^[a-f0-9]{64}$/.test(revision.value)) {
                event.preventDefault();
                feedback.textContent = 'Please preview the latest calendar changes first.';
            }
        });

        preview.addEventListener('click', async () => {
            requestController?.abort();
            requestController = new AbortController();
            const sequence = ++requestSequence;
            revision.value = '';
            apply.disabled = true;
            preview.disabled = true;
            feedback.textContent = 'Checking availability and existing bookings…';
            try {
                const response = await fetch(form.dataset.previewUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'},
                    body: new FormData(form),
                    signal: requestController.signal
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(Object.values(result.errors || {}).flat()[0] ||
                        result.message || 'Unable to preview this update.');
                }
                if (sequence !== requestSequence) return;
                if (!/^[a-f0-9]{64}$/.test(result.revision || '')) {
                    throw new Error('The preview did not return a valid calendar version.');
                }
                revision.value = result.revision;
                apply.disabled = false;
                feedback.textContent = 'Preview checked ' + result.days +
                    ' day(s), with ' + result.maximum_committed_units +
                    ' maximum committed room(s) on any day. Apply only if these changes are intended.';
            } catch (error) {
                if (sequence === requestSequence) {
                    feedback.textContent = error.name === 'AbortError'
                        ? 'Preview cancelled; please retry.'
                        : error.message;
                }
            } finally {
                if (sequence === requestSequence) preview.disabled = false;
            }
        });
    });
})();
</script>
@endsection
