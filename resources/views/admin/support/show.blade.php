@extends('admin.layouts.app')

@section('title', $ticket->reference)
@section('section-label', 'Guest support')

@section('content')
    <div class="az-admin-page-head">
        <div>
            <span class="az-eyebrow">{{ $ticket->reference }}</span>
            <h1>{{ $ticket->subject }}</h1>
            <p>{{ str($ticket->status)->replace('_',' ')->title() }} · {{ str($ticket->priority)->title() }} priority</p>
        </div>
    </div>

    <section class="az-admin-card">
        <div class="az-admin-card__header">
            <div>
                <h2>Ticket controls</h2>
                <p>Update status, priority, assignment and resolution details.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('azari.admin.support.update',$ticket) }}" class="az-form-grid az-admin-card__body">
            @csrf
            @method('PUT')

            <label class="az-field">
                <span>Status</span>
                <select name="status">
                    @foreach(['open','awaiting_staff','awaiting_guest','in_progress','escalated','resolved','closed'] as $v)
                        <option @selected($ticket->status === $v) value="{{ $v }}">{{ str($v)->replace('_',' ')->title() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="az-field">
                <span>Priority</span>
                <select name="priority">
                    @foreach(['low','normal','high','urgent'] as $v)
                        <option value="{{ $v }}" @selected($ticket->priority === $v)>{{ str($v)->title() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="az-field">
                <span>Assigned to</span>
                <select name="assigned_to">
                    <option value="">Unassigned</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->id }}" @selected($ticket->assigned_to == $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="az-field az-span-2">
                <span>Resolution note</span>
                <textarea name="resolution_note" rows="4" placeholder="Resolution note">{{ $ticket->resolution_note }}</textarea>
            </label>

            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Save ticket</button>
            </div>
        </form>
    </section>

    <section class="az-admin-card">
        <div class="az-admin-card__header">
            <div>
                <h2>Conversation</h2>
                <p>Guest messages, staff replies and internal notes.</p>
            </div>
        </div>

        <div class="az-support-thread">
            @forelse($messages as $m)
                <article class="az-support-message {{ $m->internal ? 'is-internal' : '' }}">
                    <div class="az-support-message__head">
                        <strong>{{ $m->internal ? 'Internal note' : ($m->user?->name ?: 'Resavar Support') }}</strong>
                        <span>{{ $m->created_at?->format('j M Y, g:i A') }}</span>
                    </div>
                    <p>{!! nl2br(e($m->body)) !!}</p>
                    @if($m->attachment_path)
                        <a class="text-link" href="{{ URL::temporarySignedRoute('azari.admin.support.attachment', now()->addMinutes(15), ['ticket'=>$ticket,'message'=>$m]) }}">
                            Download {{ $m->attachment_name ?: 'attachment' }}
                        </a>
                    @endif
                </article>
            @empty
                <div class="production-empty-state">No messages recorded.</div>
            @endforelse
        </div>

        <div class="az-pagination-block">{{ $messages->onEachSide(1)->links() }}</div>
    </section>

    <section class="az-admin-card">
        <div class="az-admin-card__header">
            <div>
                <h2>Reply</h2>
                <p>Send a guest-facing response or keep a note internal to staff.</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ route('azari.admin.support.reply',$ticket) }}" class="az-form-grid az-admin-card__body">
            @csrf
            <label class="az-field az-span-2">
                <span>Message</span>
                <textarea name="body" rows="6" required></textarea>
            </label>
            <label class="az-toggle">
                <input type="checkbox" name="internal" value="1">
                <span class="az-toggle-track"></span>
                <span>Internal note</span>
            </label>
            <label class="az-field">
                <span>Attachment</span>
                <input type="file" name="attachment">
            </label>
            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Send reply</button>
            </div>
        </form>
    </section>
@endsection
