
        @extends('admin.layout')
        @section('title', $ticket->reference)
        @section('content')
    <div class="space-y-6"><div><h1 class="text-3xl font-semibold">{{ $ticket->subject }}</h1><p>{{ $ticket->reference }} · {{ $ticket->status }}</p></div>
<form method="POST" action="{{ route('azari.admin.support.update',$ticket) }}" class="grid gap-3 md:grid-cols-4">@csrf @method('PUT')
<select name="status">@foreach(['open','awaiting_staff','awaiting_guest','in_progress','escalated','resolved','closed'] as $v)<option @selected($ticket->status===$v) value="{{ $v }}">{{ str($v)->replace('_',' ')->title() }}</option>@endforeach</select>
<select name="priority">@foreach(['low','normal','high','urgent'] as $v)<option @selected($ticket->priority===$v)>{{ $v }}</option>@endforeach</select>
<select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected($ticket->assigned_to===$member->id)>{{ $member->name }}</option>@endforeach</select>
<textarea name="resolution_note" placeholder="Resolution note">{{ $ticket->resolution_note }}</textarea><button>Save ticket</button></form>
@foreach($ticket->messages as $m)<article class="rounded-2xl border p-4 {{ $m->internal?'bg-amber-50':'' }}"><strong>{{ $m->internal?'Internal note':($m->user?->name?:'Azari Support') }}</strong><p>{!! nl2br(e($m->body)) !!}</p>@if($m->attachment_path)<a href="{{ URL::temporarySignedRoute('azari.admin.support.attachment',now()->addMinutes(15),['ticket'=>$ticket,'message'=>$m]) }}">Download {{ $m->attachment_name?:'attachment' }}</a>@endif</article>@endforeach
<form method="POST" enctype="multipart/form-data" action="{{ route('azari.admin.support.reply',$ticket) }}" class="space-y-3">@csrf<textarea name="body" required></textarea><label><input type="checkbox" name="internal" value="1"> Internal note</label><input type="file" name="attachment"><button>Send reply</button></form>
</div>
        
@endsection

    