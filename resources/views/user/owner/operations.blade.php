@extends('layouts.user')
@section('title','Operations')
@section('kicker','Property Centre')
@section('page_title',$property->name.' operations')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Operations board</h2><p class="az-user-panel-subtitle">Housekeeping, arrivals, inspections, maintenance and handovers tied to this property.</p></div></header>
<div class="az-user-panel-body">
    <dl class="az-user-detail-grid" aria-label="Property operations summary">
        @foreach([
            'Active tasks' => 'active',
            'Overdue tasks' => 'overdue',
            'Blocked tasks' => 'blocked',
            'High-priority tasks' => 'high_priority',
            'Unassigned tasks' => 'unassigned',
        ] as $label => $metric)
            <div><dt>{{ $label }}</dt><dd><strong>{{ number_format((int) ($metrics->{$metric} ?? 0)) }}</strong></dd></div>
        @endforeach
    </dl>
    <form method="POST" action="{{ route('user.owner.phase2.operations.tasks.store',$property) }}" class="az-form-grid">@csrf
<label><span>Task</span><input name="title" required maxlength="160"></label>
<label><span>Type</span><select name="type"><option>arrival</option><option>housekeeping</option><option>inspection</option><option>maintenance</option><option>handover</option></select></label>
<label><span>Priority</span><select name="priority"><option>normal</option><option>low</option><option>high</option><option>urgent</option></select></label>
<label><span>Booking</span><select name="booking_id"><option value="">Not tied to a booking</option>@foreach($bookings as $booking)<option value="{{ $booking->id }}">{{ $booking->reference }} · {{ $booking->check_in?->format('j M') }}</option>@endforeach</select></label>
<label><span>Due</span><input type="datetime-local" name="due_at"></label>
<label><span>Assign to</span><select name="assigned_to"><option value="">Unassigned</option>@foreach($staffUsers as $staff)<option value="{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select></label>
<label style="grid-column:1/-1"><span>Checklist <small>(optional, one item per line)</small></span><textarea name="checklist_items" rows="4" maxlength="5000" placeholder="Confirm room is clean&#10;Check linen and towels&#10;Verify maintenance issues are cleared"></textarea></label>
<label style="grid-column:1/-1"><span>Internal notes</span><textarea name="notes" rows="3"></textarea></label>
<button class="az-user-button az-user-button--dark" type="submit">Create task</button></form></div></section>
@if($errors->has('status'))<p role="alert" class="az-user-status" style="margin-top:12px">{{ $errors->first('status') }}</p>@endif
<section class="az-user-panel" style="margin-top:18px"><div class="az-user-panel-body">
@if($tasks->isEmpty())<div class="az-user-empty"><h3>No operational tasks</h3></div>@else
<div class="az-user-list">@foreach($tasks as $task)<div class="az-user-list-item"><div><h3>{{ $task->title }}</h3><p>{{ Str::headline($task->type) }} · {{ Str::headline($task->priority) }} @if($task->due_at) · due {{ $task->due_at->format('j M, H:i') }} @endif</p><p>{{ $task->notes }}</p>
@if($task->evidence_path)<p><a href="{{ route('user.owner.phase2.operations.tasks.evidence',[$property,$task]) }}">View private evidence</a></p>@endif
</div>
<form method="POST" enctype="multipart/form-data" action="{{ route('user.owner.phase2.operations.tasks.update',[$property,$task]) }}" class="az-form-grid">@csrf @method('PATCH')
<input type="hidden" name="version" value="{{ $task->version }}">
<label><span>Status</span><select name="status" aria-label="Task status">@foreach(['open','in_progress','blocked','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($task->status===$status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
<label><span>Assigned staff</span><select name="assigned_to" aria-label="Assigned staff"><option value="">Unassigned</option>@foreach($staffUsers as $staff)<option value="{{ $staff->id }}" @selected((int)$task->assigned_to===(int)$staff->id)>{{ $staff->name }}</option>@endforeach</select></label>
@if(!empty($task->checklist))
<fieldset style="grid-column:1/-1"><legend>Completion checklist</legend>
@foreach($task->checklist as $index => $item)
<label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="checklist_completed[]" value="{{ $index }}" @checked((bool)($item['done'] ?? false))><span>{{ $item['label'] ?? '' }}</span></label>
@endforeach
</fieldset>
@endif
<label style="grid-column:1/-1"><span>Photo evidence <small>(optional, JPG/PNG, private)</small></span><input type="file" name="evidence" accept="image/jpeg,image/png"></label>
<input type="hidden" name="notes" value="{{ $task->notes }}"><button class="az-user-button" type="submit">Update</button></form></div>@endforeach</div>{{ $tasks->links() }}
@endif</div></section>
@endsection
