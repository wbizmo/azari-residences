<x-public.layout>
    @php
        $submittedReference = trim((string) request('reference', ''));
        $hasSearched = request()->has('reference') && $submittedReference !== '';
    @endphp

    <section class="verification-page section-shell">
        <div class="verification-card">
            <header class="verification-card__header">
                <p class="eyebrow">Reservation lookup</p>
                <h1>Verify your booking</h1>
                <p>Enter the booking reference from your confirmation message.</p>
            </header>

            <form method="GET" action="{{ route('bookings.verify') }}" class="azari-form" data-working-form novalidate>
                <div class="field-group">
                    <label for="booking-reference">Booking reference</label>
                    <input
                        id="booking-reference"
                        name="reference"
                        type="text"
                        value="{{ old('reference', $submittedReference) }}"
                        autocomplete="off"
                        inputmode="text"
                        placeholder="e.g. AZR-8F2K91"
                        required
                    >
                    @error('reference')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="button button--primary" data-working-text="Checking…">
                    <span data-button-label>Verify booking</span>
                </button>
            </form>

            @if(isset($booking) && $booking)
                <div class="verification-result verification-result--success" role="status">
                    <div>
                        <span class="verification-result__label">Reference</span>
                        <strong>{{ $booking->reference }}</strong>
                    </div>
                    <div>
                        <span class="verification-result__label">Status</span>
                        <strong>{{ ucfirst((string) $booking->status) }}</strong>
                    </div>
                    @if(!empty($booking->check_in) || !empty($booking->check_in_date))
                        <div>
                            <span class="verification-result__label">Check-in</span>
                            <strong>{{ $booking->check_in ?? $booking->check_in_date }}</strong>
                        </div>
                    @endif
                    @if(!empty($booking->check_out) || !empty($booking->check_out_date))
                        <div>
                            <span class="verification-result__label">Check-out</span>
                            <strong>{{ $booking->check_out ?? $booking->check_out_date }}</strong>
                        </div>
                    @endif
                </div>
            @elseif($hasSearched)
                <div class="verification-result verification-result--empty" role="status">
                    <strong>Booking not found</strong>
                    <p>Check the reference and try again.</p>
                </div>
            @endif
        </div>
    </section>
</x-public.layout>
