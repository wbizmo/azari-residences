<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\SyncChannelConnection;
use App\Models\ChannelConnection;
use App\Models\Property;
use App\Services\Channels\ChannelConnectionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerChannelConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $properties = Property::query()->with('accommodationTypes')->where('owner_id',$request->user()->id)->orderBy('name')->get();
        return view('user.owner.channels', [
            'connections' => ChannelConnection::query()->with(['property','accommodationType'])->whereIn('property_id',$properties->pluck('id'))->latest()->get(),
            'properties' => $properties,
        ]);
    }
    public function store(Request $request): RedirectResponse
    {
        $data = $this->payload($request);
        ChannelConnection::query()->create($data);
        return back()->with('success','Channel connection created. Inventory remains closed until the first successful sync.');
    }
    public function sync(Request $request, ChannelConnection $connection): RedirectResponse
    {
        $this->authorizeConnection($request, $connection);
        abort_unless($connection->is_active && ! in_array($connection->status,
            [ChannelConnectionLifecycleService::PENDING, ChannelConnectionLifecycleService::DISCONNECTED], true), 409);
        SyncChannelConnection::dispatch($connection->id);
        return back()->with('success','Synchronization queued.');
    }
    public function destroy(Request $request, ChannelConnection $connection, ChannelConnectionLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorizeConnection($request, $connection);
        $lifecycle->disconnect($connection, $request->user()->getKey());
        return back()->with('success', 'Channel disconnected. External bookings remain protected until reconciled.');
    }
    private function payload(Request $request): array
    {
        $data=$request->validate(['property_id'=>['required','integer'],'accommodation_type_id'=>['nullable','integer'],'provider'=>['required',Rule::in(['ical'])],'name'=>['required','string','max:120'],'import_url'=>['required','url:http,https','max:2000'],'stale_after_minutes'=>['required','integer','min:15','max:10080']]);
        $property=Property::query()->whereKey($data['property_id'])->where('owner_id',$request->user()->id)->firstOrFail();
        if (filled($data['accommodation_type_id'] ?? null)) abort_unless($property->accommodationTypes()->whereKey($data['accommodation_type_id'])->exists(),422);
        return [...$data,'is_active'=>true,'fail_closed'=>true];
    }
    private function authorizeConnection(Request $request, ChannelConnection $connection): void { abort_unless(Property::query()->whereKey($connection->property_id)->where('owner_id',$request->user()->id)->exists(),404); }
}
