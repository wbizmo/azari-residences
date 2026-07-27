@extends('layouts.user')

@section('title', 'Service requests')
@section('page_title', 'Service requests')
@section('kicker', 'Guest services')

@section('content')
<section class="az-user-detail-grid">
    <article class="az-user-panel">
        <header class="az-user-panel-header">
            <div>
                <h2 class="az-user-panel-title">New request</h2>
                <p>Send a request linked to one of your eligible bookings.</p>
            </div>
        </header>

        <div class="az-user-panel-body">
            @if($bookings->isEmpty())
                <div class="az-user-empty">
                    <span class="material-symbols-outlined" aria-hidden="true">event_busy</span>
                    <h3>No eligible booking</h3>
                    <p>A current booking is required before a service request can be submitted.</p>
                </div>
            @else
                <form method="post" enctype="multipart/form-data" action="{{ route('user.service-requests.store') }}" class="az-user-form az-user-form-grid">
                    @csrf
                    <label class="az-user-field">
                        <span>Booking</span>
                        <select name="booking_id" required>
                            @foreach($bookings as $booking)
                                <option value="{{ $booking->id }}">{{ $booking->reference }} · {{ $booking->property?->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="az-user-field">
                        <span>Request type</span>
                        <select name="type" required>
                            @foreach(['concierge', 'housekeeping', 'restaurant', 'airport_transfer', 'maintenance', 'general'] as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="az-user-field az-user-field--full">
                        <span>Subject</span>
                        <input name="title" required maxlength="160" value="{{ old('title') }}">
                    </label>

                    <label class="az-user-field">
                        <span>Requested date and time</span>
                        <input type="datetime-local" name="requested_at" value="{{ old('requested_at') }}">
                    </label>

                    <label class="az-user-field">
                        <span>Optional image</span>
                        <input type="file" name="attachment" accept="image/*">
                    </label>

                    <label class="az-user-field az-user-field--full">
                        <span>Notes</span>
                        <textarea name="notes" rows="5">{{ old('notes') }}</textarea>
                    </label>

                    <div class="az-user-field az-user-field--full">
                        <button class="az-user-button az-user-button--primary" type="submit">Submit request</button>
                    </div>
                </form>
            @endif
        </div>
    </article>

    <article class="az-user-panel">
        <header class="az-user-panel-header">
            <div>
                <h2 class="az-user-panel-title">Request history</h2>
                <p>Track every request and its current status.</p>
            </div>
        </header>

        <div class="az-user-panel-body">
            <div class="az-user-service-history">
                @forelse($requests as $request)
                    <a class="az-user-list-item" href="{{ route('user.service-requests.show', $request) }}">
                        <div>
                            <h3>{{ $request->reference }} · {{ $request->title }}</h3>
                            <p>{{ ucwords(str_replace('_', ' ', $request->type)) }} · {{ $request->booking?->reference }}</p>
                        </div>
                        <span class="az-user-status">{{ ucwords(str_replace('_', ' ', $request->status)) }}</span>
                    </a>
                @empty
                    <div class="az-user-empty">
                        <span class="material-symbols-outlined" aria-hidden="true">room_service</span>
                        <p>No service requests have been submitted.</p>
                    </div>
                @endforelse
            </div>

            <div class="az-user-pagination az-user-service-pagination">
                <span>
                    @if(method_exists($requests, 'total'))
                        Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} requests
                    @else
                        {{ $requests->count() }} requests shown
                    @endif
                </span>
                {{ $requests->onEachSide(1)->links() }}
            </div>
        </div>
    </article>
</section>
@endsection
