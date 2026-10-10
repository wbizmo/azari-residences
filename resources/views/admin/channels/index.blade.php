@extends('admin.layouts.app')
@section('title','Channel synchronization')
@section('content')
<div class="admin-content"><h1>Channel synchronization</h1><p>External calendars are imported as separate inventory blocks. Stale connections fail closed by default.</p>
<form method="POST" action="{{ route('azari.admin.channels.store') }}" class="az-user-panel" style="padding:20px">@csrf
<label>Property <select name="property_id" required>@foreach($properties as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select></label>
<label>Room mapping <select name="accommodation_type_id"><option value="">Property-wide (review before use)</option>@foreach($properties as $p)<optgroup label="{{ $p->name }}">@foreach($p->accommodationTypes as $type)<option value="{{ $type->id }}">{{ $type->name }} ({{ $type->currency }})</option>@endforeach</optgroup>@endforeach</select></label>
<label>Name <input name="name" required placeholder="Airbnb iCal"></label><input type="hidden" name="provider" value="ical">
<label>Import URL <input type="url" name="import_url" required></label><label>Stale after minutes <input type="number" name="stale_after_minutes" value="180" min="15"></label>
<label><input type="checkbox" name="is_active" value="1" checked> Active</label><label><input type="checkbox" name="fail_closed" value="1" checked> Fail closed when stale</label>
<button class="button button-primary">Add connection</button></form>
<div class="az-user-panel" style="margin-top:20px;padding:20px"><table style="width:100%"><thead><tr><th>Property</th><th>Channel</th><th>Status</th><th>Last success</th><th>Actions</th></tr></thead><tbody>@forelse($connections as $connection)<tr><td>{{ $connection->property?->name }}</td><td>{{ $connection->name }}</td><td>{{ $connection->status }}</td><td>{{ $connection->last_successful_sync_at?->format('j M Y H:i T') ?? 'Never' }}</td><td><form method="POST" action="{{ route('azari.admin.channels.sync',$connection) }}" style="display:inline">@csrf<button>Sync</button></form> <form method="POST" action="{{ route('azari.admin.channels.destroy',$connection) }}" style="display:inline">@csrf @method('DELETE')<button>Remove</button></form></td></tr>@empty<tr><td colspan="5">No channel connections yet.</td></tr>@endforelse</tbody></table>{{ $connections->links() }}</div></div>
@endsection
