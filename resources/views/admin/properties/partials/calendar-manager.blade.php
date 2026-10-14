<section class="az-admin-subsection" style="margin-top:24px">
    <div class="az-form-section-head">
        <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
        <div>
            <h2>Availability and nightly rate calendar</h2>
            <p>Calendar changes take effect only after preview. Existing booking prices are never rewritten.</p>
        </div>
    </div>
    <nav aria-label="Calendar navigation" style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0">
        <a class="button button-secondary" href="{{ route('azari.admin.properties.commercial', ['property' => $property, 'calendar_date' => $inventoryBoard['previous'], 'calendar_view' => $inventoryBoard['view']]) }}">Previous</a>
        <a class="button button-secondary" href="{{ route('azari.admin.properties.commercial', ['property' => $property, 'calendar_date' => $inventoryBoard['next'], 'calendar_view' => $inventoryBoard['view']]) }}">Next</a>
        <a class="button button-secondary" href="{{ route('azari.admin.properties.commercial', ['property' => $property, 'calendar_view' => $inventoryBoard['view'] === 'week' ? 'month' : 'week']) }}">Switch to {{ $inventoryBoard['view'] === 'week' ? 'month' : 'week' }} view</a>
    </nav>
    @forelse($inventoryBoard['types'] as $row)
        @php($type = $row['type'])
        <div class="az-form-section-head" style="margin-top:18px">
            <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>
            <div>
                <h3>{{ $type->name }} ({{ $type->currency }})</h3>
                <p>{{ $type->total_inventory }} physical unit(s). Availability is net of bookings, holds, blocks and maintenance.</p>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table class="admin-table">
                <thead><tr><th scope="col">Date</th><th scope="col">Sellable</th><th scope="col">Booked</th><th scope="col">Held</th><th scope="col">Remaining</th><th scope="col">Restrictions</th><th scope="col">Nightly override</th></tr></thead>
                <tbody>
                @foreach($row['dates'] as $day => $cell)
                    <tr>
                        <th scope="row">{{ $day }}</th>
                        <td>{{ $cell['sellable'] }}</td>
                        <td>{{ $cell['booked'] }}</td>
                        <td>{{ $cell['held'] }}</td>
                        <td>{{ $cell['remaining'] }}</td>
                        <td>{{ $cell['stop_sell'] ? 'Closed' : 'Open' }}</td>
                        <td>{{ $cell['price_override'] === null ? 'Default' : $type->currency.' '.number_format((float) $cell['price_override'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST"
              action="{{ route('azari.admin.properties.commercial.calendar.apply', [$property, $type]) }}"
              data-admin-calendar-form
              data-preview-url="{{ route('azari.admin.properties.commercial.calendar.preview', [$property, $type]) }}"
              class="admin-form az-form-grid" style="margin-top:18px">
            @csrf
            <label class="az-field"><span>From date</span><input name="from_date" type="date" required></label>
            <label class="az-field"><span>To date (inclusive)</span><input name="to_date" type="date" required></label>
            <label class="az-field"><span>Sellable units (leave blank to keep)</span><input name="sellable_inventory" type="number" min="0" max="{{ $type->total_inventory }}"></label>
            <label class="az-field"><span>Maintenance units</span><input name="maintenance_inventory" type="number" min="0" max="{{ $type->total_inventory }}"></label>
            <label class="az-field"><span>Minimum stay</span><input name="minimum_stay" type="number" min="1" max="730"></label>
            <label class="az-field"><span>Maximum stay</span><input name="maximum_stay" type="number" min="1" max="730"></label>
            <label class="az-field"><span>Nightly override ({{ $type->currency }})</span><input name="price_override" type="number" min="0" step="0.01"></label>
            @foreach(['stop_sell' => 'Stop new sales', 'closed_to_arrival' => 'Closed to arrival', 'closed_to_departure' => 'Closed to departure'] as $key => $label)
                <label class="az-field"><span>{{ $label }}</span>
                    <select name="{{ $key }}">
                        <option value="">Keep current</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </label>
            @endforeach
            <input type="hidden" name="expected_revision" value="">
            <div class="az-form-actions az-span-2" style="display:flex;gap:12px;flex-wrap:wrap">
                <button class="button button-secondary" type="button" data-calendar-preview>Preview availability impact</button>
                <button class="button button-primary" type="submit" data-calendar-apply disabled>Apply reviewed changes</button>
            </div>
            <p class="az-span-2" role="status" aria-live="polite" data-calendar-feedback>Preview the selected dates and changes before applying.</p>
        </form>
    @empty
        <p>Add an accommodation type above to configure room-night inventory.</p>
    @endforelse
</section>
@if($calendarLogs->isNotEmpty())
<section class="az-admin-subsection" style="margin-top:24px">
    <div class="az-form-section-head">
        <span class="material-symbols-outlined" aria-hidden="true">history</span>
        <div><h2>Recent calendar changes</h2><p>Undo refuses changes that would overwrite subsequent edits or disrupt committed rooms.</p></div>
    </div>
    <div style="overflow-x:auto">
        <table class="admin-table">
            <thead><tr><th scope="col">Dates</th><th scope="col">Source</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
            <tbody>
            @foreach($calendarLogs as $log)
                <tr>
                    <td>{{ $log->from_date?->toDateString() }} to {{ $log->to_date?->toDateString() }}</td>
                    <td>{{ ucfirst($log->source) }}</td>
                    <td>{{ $log->reverted_at ? 'Undone' : 'Applied' }}</td>
                    <td>
                        @if(! $log->reverted_at && $log->after_snapshot)
                            <form method="POST" action="{{ route('azari.admin.properties.commercial.calendar.undo', [$property, $log]) }}">
                                @csrf
                                <button type="submit" class="button button-secondary">Undo safely</button>
                            </form>
                        @else
                            <span>Unavailable</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
<script>
(() => {
    'use strict';
    document.querySelectorAll('[data-admin-calendar-form]').forEach(form => {
        const preview = form.querySelector('[data-calendar-preview]');
        const submit = form.querySelector('[data-calendar-apply]');
        const revision = form.querySelector('[name="expected_revision"]');
        const feedback = form.querySelector('[data-calendar-feedback]');
        let controller = null;
        const invalidate = () => {
            controller?.abort();
            revision.value = '';
            submit.disabled = true;
            feedback.textContent = 'Changes detected. Preview again before applying.';
        };
        form.addEventListener('input', invalidate);
        form.addEventListener('change', invalidate);
        form.addEventListener('submit', event => {
            if (!revision.value) {
                event.preventDefault();
                feedback.textContent = 'A current preview is required before applying.';
            }
        });
        preview.addEventListener('click', async () => {
            if (!form.reportValidity()) return;
            controller?.abort();
            controller = new AbortController();
            revision.value = '';
            submit.disabled = true;
            preview.disabled = true;
            feedback.textContent = 'Checking live bookings, holds and inventory…';
            try {
                const response = await fetch(form.dataset.previewUrl, {
                    method: 'POST', body: new FormData(form),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin', signal: controller.signal,
                });
                const json = await response.json();
                if (!response.ok) throw new Error(
                    Object.values(json.errors || {}).flat().join(' ') ||
                    json.message || 'Cannot preview these changes.'
                );
                revision.value = json.revision;
                submit.disabled = false;
                feedback.textContent = 'Preview: ' + json.days + ' dates; maximum ' +
                    json.maximum_committed_units + ' committed units on any date. Review the values, then apply.';
            } catch (error) {
                if (error.name !== 'AbortError') feedback.textContent = error.message;
            } finally {
                preview.disabled = false;
            }
        });
    });
})();
</script>