@extends('layouts.user')
@section('title', 'Receipts and invoices')
@section('kicker', 'Document centre')
@section('page_title', 'Receipts & invoices')
@section('content')
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Financial documents</h2>
            <p class="az-user-panel-subtitle">View booking confirmations, invoices and available payment receipts.</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        @if($bookings->isEmpty())
            <div class="az-user-empty">
                <span class="material-symbols-outlined" aria-hidden="true">description</span>
                <p>No bookings are available.</p>
            </div>
        @else
            <div class="az-user-list">
                @foreach($bookings as $booking)
                    @php
                        $payment = $booking->payments->first();
                        $receiptAvailable = $booking->receiptAvailable();
                    @endphp
                    <div class="az-user-list-item" style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:16px">
                        <div style="min-width:0;flex:1 1 230px">
                            <h3 style="overflow-wrap:anywhere">{{ $booking->reference }}</h3>
                            <p>{{ $booking->property?->name ?? 'Resavar Stay' }}</p>
                            <p>
                                {{ $booking->status === 'cancelled' ? 'Cancelled booking' : 'Booking record available' }}
                                · Receipt: {{ $receiptAvailable ? ($payment?->receipt_number ?: 'Available to download') : 'Not yet available' }}
                            </p>
                        </div>
                        <div class="az-user-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                            <a class="az-user-button az-user-button--light" href="{{ route('user.bookings.show', $booking->reference) }}">Booking details</a>
                            <a class="az-user-button az-user-button--light" href="{{ route('user.bookings.documents', [$booking->reference, 'confirmation']) }}" target="_blank" rel="noopener noreferrer">Confirmation PDF</a>
                            <a class="az-user-button az-user-button--light" href="{{ route('user.bookings.documents', [$booking->reference, 'invoice']) }}" target="_blank" rel="noopener noreferrer">Invoice PDF</a>
                            @if($receiptAvailable)
                                <a class="az-user-button az-user-button--dark" href="{{ route('user.bookings.documents', [$booking->reference, 'receipt']) }}" target="_blank" rel="noopener noreferrer">Receipt PDF</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $bookings->links() }}
        @endif
    </div>
</section>
@endsection
