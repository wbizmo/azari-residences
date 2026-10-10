<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncChannelConnection;
use App\Models\AuditLog;
use App\Models\ChannelConnection;
use App\Models\Property;
use App\Services\Channels\ChannelConnectionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChannelConnectionController extends Controller
{
    public function index(): View
    {
        return view('admin.channels.index', [
            'connections' => ChannelConnection::query()->with(['property','accommodationType'])->latest()->paginate(20),
            'properties' => Property::query()->with('accommodationTypes')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $connection = ChannelConnection::query()->create($data);
        AuditLog::record('channel.connection_created', $connection, [], ['provider'=>$connection->provider,'property_id'=>$connection->property_id]);
        return back()->with('success', 'Channel connection created. It will fail closed until the first successful sync.');
    }

    public function update(Request $request, ChannelConnection $connection, ChannelConnectionLifecycleService $lifecycle): RedirectResponse
    {
        abort_if(in_array($connection->status, [ChannelConnectionLifecycleService::PENDING,
            ChannelConnectionLifecycleService::DISCONNECTED], true), 409,
            'Disconnected channels require a new certified connection.');
        if (! $request->boolean('is_active', true)) {
            $lifecycle->disconnect($connection, $request->user()?->getKey());
            return back()->with('success', 'Channel disconnected without releasing existing reservations.');
        }
        $old = $connection->only(['name','is_active','fail_closed','stale_after_minutes']);
        $connection->update($this->validatePayload($request, $connection));
        AuditLog::record('channel.connection_updated', $connection, $old, $connection->only(array_keys($old)));
        return back()->with('success', 'Channel connection updated.');
    }

    public function sync(ChannelConnection $connection): RedirectResponse
    {
        abort_unless($connection->is_active && ! in_array($connection->status,
            [ChannelConnectionLifecycleService::PENDING, ChannelConnectionLifecycleService::DISCONNECTED], true), 409);
        SyncChannelConnection::dispatch($connection->getKey());
        return back()->with('success', 'Channel synchronization queued.');
    }

    public function destroy(ChannelConnection $connection, ChannelConnectionLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->disconnect($connection, auth()->id());
        return back()->with('success', 'Channel disconnected. Unreconciled reservations remain blocked.');
    }

    private function validatePayload(Request $request, ?ChannelConnection $connection = null): array
    {
        $data = $request->validate([
            'property_id' => ['required','integer','exists:properties,id'],
            'accommodation_type_id' => ['nullable','integer','exists:accommodation_types,id'],
            'provider' => ['required',Rule::in(['ical'])],
            'name' => ['required','string','max:120'],
            'import_url' => ['required','url:http,https','max:2000'],
            'stale_after_minutes' => ['required','integer','min:15','max:10080'],
        ]);
        if (filled($data['accommodation_type_id'] ?? null)) {
            abort_unless(\App\Models\AccommodationType::query()->whereKey($data['accommodation_type_id'])->where('property_id',$data['property_id'])->exists(), 422);
        }
        $data['is_active'] = $request->boolean('is_active', true);
        $data['fail_closed'] = true; // External inventory must fail closed.
        return $data;
    }
}
