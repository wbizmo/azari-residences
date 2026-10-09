@extends('layouts.user')
@section('title','Operations')
@section('kicker','Property Centre')
@section('page_title',$property->name.' operations')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Operations board</h2><p class="az-user-panel-subtitle">Housekeeping, arrivals, inspections, maintenance and handovers tied to this property.</p></div></header>
<div class="az-user-panel-body"><form method="POST" action="{{ route('user.owner.phase2.operations.tasks.store',$property) }}" class="az-form-grid">@csrf
<label><span>Task</span><input name="title" required maxlength="160"></label>
<label><span>Type</span><select name="type"><option>arrival</option><option>housekeeping</option><option>inspection</option><option>maintenance</option><option>handover</option></select></label>
<label><span>Priority</span><select name="priority"><option>normal</option><option>low</option><option>high</option><option>urgent</option></select></label>
<label><span>Booking</span><select name="booking_id"><option value="">Not tied to a booking</option>@foreach($bookings as $booking)<option value="{{ $booking->id }}">{{ $booking->reference }} · {{ $booking->check_in?->format('j M') }}</option>@endforeach</select></label>
<label><span>Due</span><input type="datetime-local" name="due_at"></label>
<label><span>Assign to</span><select name="assigned_to"><option value="">Unassigned</option>@foreach($staffUsers as $staff)<option value="{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select></label>
<label style="grid-column:1/-1"><span>Internal notes</span><textarea name="notes" rows="3"></textarea></label>
<button class="az-user-button az-user-button--dark" type="submit">Create task</button></form></div></section>
<section class="az-user-panel" style="margin-top:18px"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Arrival readiness</h2><p class="az-user-panel-subtitle">Release rooms only after blocking housekeeping, inspection and maintenance work is complete.</p></div></header><div class="az-user-panel-body"><div class="az-user-list">
@forelse($bookings as $booking)
<div class="az-user-list-item"><div><strong>{{ $booking->reference }}</strong><p>{{ $booking->guest_name }} · {{ $booking->check_in?->format('j M Y') }} @if($booking->room_ready_at) · Room ready {{ $booking->room_ready_at->diffForHumans() }} @else · Not released @endif</p></div>
@if($booking->room_ready_at)
<form method="POST" action="{{ route('user.owner.phase2.room-ready.revoke',[$property,$booking]) }}">@csrf @method('DELETE')<button class="az-user-button az-user-button--light" type="submit">Revoke readiness</button></form>
@else
<form method="POST" action="{{ route('user.owner.phase2.room-ready',[$property,$booking]) }}">@csrf<button class="az-user-button az-user-button--dark" type="submit">Mark room ready</button></form>
@endif
</div>
@empty<div class="az-user-empty"><p>No upcoming active bookings.</p></div>@endforelse
</div></div></section>

<section class="az-user-panel" style="margin-top:18px"><div class="az-user-panel-body">
@if($tasks->isEmpty())<div class="az-user-empty"><h3>No operational tasks</h3></div>@else
<div class="az-user-list">@foreach($tasks as $task)<div class="az-user-list-item"><div><h3>{{ $task->title }}</h3><p>{{ Str::headline($task->type) }} · {{ Str::headline($task->priority) }} @if($task->due_at) · due {{ $task->due_at->format('j M, H:i') }} @endif</p><p>{{ $task->notes }}</p></div>
<form method="POST" action="{{ route('user.owner.phase2.operations.tasks.update',[$property,$task]) }}">@csrf @method('PATCH')<select name="status" aria-label="Task status">@foreach(['open','in_progress','blocked','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($task->status===$status)>{{ Str::headline($status) }}</option>@endforeach</select><select name="assigned_to" aria-label="Assigned staff"><option value="">Unassigned</option>@foreach($staffUsers as $staff)<option value="{{ $staff->id }}" @selected((int)$task->assigned_to===(int)$staff->id)>{{ $staff->name }}</option>@endforeach</select><input type="hidden" name="notes" value="{{ $task->notes }}"><button class="az-user-button" type="submit">Update</button></form></div>@endforeach</div>{{ $tasks->links() }}
@endif</div></section>
@endsection
