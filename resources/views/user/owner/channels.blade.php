@extends('layouts.user')
@section('title','Channel calendars')
@section('kicker','Property Centre')
@section('page_title','Channel calendars')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">External calendars</h2><p class="az-user-panel-subtitle">Import external reservations without coupling Resavar inventory to a single provider.</p></div></header><div class="az-user-panel-body">
<form method="POST" action="{{ route('user.owner.channels.store') }}" class="az-user-form">@csrf<select name="property_id" required>@foreach($properties as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select><input type="hidden" name="provider" value="ical"><input name="name" placeholder="Calendar name" required><input type="url" name="import_url" placeholder="https://…/calendar.ics" required><input type="number" name="stale_after_minutes" value="180" min="15"><button class="az-user-button az-user-button--dark">Connect calendar</button></form>
@foreach($connections as $connection)<article class="az-user-list-item"><div><h3>{{ $connection->name }}</h3><p>{{ $connection->property?->name }} · {{ ucwords(str_replace('_',' ',$connection->status)) }} · last success {{ $connection->last_successful_sync_at?->format('j M Y H:i T') ?? 'never' }}</p><p>Export URL: <code>{{ route('channels.export',$connection->export_token) }}</code></p></div><div><form method="POST" action="{{ route('user.owner.channels.sync',$connection) }}">@csrf<button class="az-user-button">Sync</button></form><form method="POST" action="{{ route('user.owner.channels.destroy',$connection) }}">@csrf @method('DELETE')<button class="az-user-button">Remove</button></form></div></article>@endforeach
</div></section>
@endsection
