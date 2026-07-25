<x-public.layout title="Verify booking">
    @php
        $submittedReference = trim((string) request('reference', ''));
        $hasSearched = request()->has('reference') && $submittedReference !== '';
    @endphp
    <section class="az-verification-page">
        <div class="az-verification-card">
            <h1>Verify booking</h1>
            <form method="GET" action="{{ route('bookings.verify') }}" class="az-form" data-az-working-form>
                <div class="az-field"><label for="booking-reference">Booking reference</label><input id="booking-reference" name="reference" type="text" value="{{ old('reference', $submittedReference) }}" autocomplete="off" spellcheck="false" placeholder="AZR-XXXXXXXXXX" required>@error('reference')<p class="az-field-error">{{ $message }}</p>@enderror</div>
                <button type="submit" class="button button--primary" data-az-working-text="Checking…"><span data-az-button-label>Verify booking</span></button>
            </form>
            @if(isset($booking) && $booking)
                <div class="az-verification-result" role="status"><dl><div><dt>Reference</dt><dd>{{ $booking->reference }}</dd></div><div><dt>Status</dt><dd>{{ ucfirst(str_replace('_', ' ', (string) $booking->status)) }}</dd></div></dl></div>
            @elseif($hasSearched)
                <div class="az-verification-result az-verification-result--error" role="status"><p>Booking not found. Check the reference and try again.</p></div>
            @endif
        </div>
    </section>
</x-public.layout>
