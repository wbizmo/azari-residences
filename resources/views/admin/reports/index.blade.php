@extends('admin.layouts.app')

@section('title', 'Reports')
@section('section-label', 'Analytics')

@section('content')
    <div class="az-admin-page-head">
        <div>
            <span class="az-eyebrow">Operations intelligence</span>
            <h1>Reports and exports</h1>
            <p>Resarva operational timezone: {{ config('azari.timezone','Africa/Lagos') }}</p>
        </div>
    </div>

    <section class="az-admin-card">
        <div class="az-admin-card__header">
            <div>
                <h2>Report filters</h2>
                <p>Refine the dataset before exporting or printing.</p>
            </div>
        </div>

        <form method="GET" class="az-form-grid az-admin-card__body">
            <label class="az-field">
                <span>Report type</span>
                <select name="type">
                    @foreach($types as $item)
                        <option value="{{ $item }}" @selected($type === $item)>{{ str($item)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="az-field">
                <span>From</span>
                <input type="date" name="from" value="{{ request('from') }}">
            </label>

            <label class="az-field">
                <span>To</span>
                <input type="date" name="to" value="{{ request('to') }}">
            </label>

            <label class="az-field">
                <span>Status</span>
                <input name="status" value="{{ request('status') }}" placeholder="Any status">
            </label>

            <label class="az-field">
                <span>Provider</span>
                <input name="provider" value="{{ request('provider') }}" placeholder="Any provider">
            </label>

            <label class="az-field">
                <span>Currency</span>
                <input name="currency" value="{{ request('currency') }}" placeholder="Any currency">
            </label>

            <label class="az-field">
                <span>Booking ID</span>
                <input name="booking_id" value="{{ request('booking_id') }}" placeholder="Booking ID">
            </label>

            <div class="az-form-actions">
                <button class="button button-primary" type="submit">Apply filters</button>
            </div>
        </form>
    </section>

    <section class="az-admin-card">
        <div class="az-admin-card__header">
            <div>
                <h2>Export results</h2>
                <p>Download the filtered report or open a print-ready view.</p>
            </div>
            <div class="az-admin-actions">
                @foreach(['csv','excel','pdf'] as $format)
                    <a class="button button-secondary" href="{{ route('azari.admin.reports.export', array_merge(request()->query(), ['format' => $format])) }}">{{ strtoupper($format) }}</a>
                @endforeach
                <a class="button button-secondary" target="_blank" rel="noopener" href="{{ route('azari.admin.reports.print', request()->query()) }}">Print</a>
            </div>
        </div>

        <div class="az-admin-table-wrap">
            <table class="az-admin-table">
                @if($rows->count())
                    <thead>
                        <tr>
                            @foreach(array_keys($rows->first()->getAttributes()) as $key)
                                <th>{{ str($key)->replace('_',' ')->title() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach($row->getAttributes() as $value)
                                    <td>{{ is_scalar($value) || is_null($value) ? $value : json_encode($value) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @else
                    <tbody>
                        <tr><td>No report records match the selected filters.</td></tr>
                    </tbody>
                @endif
            </table>
        </div>

        <div class="az-pagination-block">{{ $rows->links() }}</div>
    </section>
@endsection
