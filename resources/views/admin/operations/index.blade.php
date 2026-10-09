
        @extends('admin.layout')
        @section('title', 'System health')
        @section('content')
    <div class="space-y-8">
<div><h1 class="text-3xl font-semibold">System health and operations</h1><p class="text-sm opacity-70">Safe summaries only. Secrets and raw environment values are never displayed.</p></div>
<section><h2 class="text-xl font-semibold">Health</h2><div class="mt-3 grid gap-3 md:grid-cols-3">@foreach($health as $key=>$value)<div class="rounded-2xl border p-4"><div class="text-xs uppercase opacity-60">{{ str($key)->replace('_',' ') }}</div><div class="mt-2 break-words font-medium">{{ is_array($value)?json_encode($value):($value instanceof \DateTimeInterface?$value->format('c'):var_export($value,true)) }}</div></div>@endforeach</div></section>
<section><div class="flex items-center justify-between"><h2 class="text-xl font-semibold">Backups</h2><form method="POST" action="{{ route('azari.admin.system-health.backup') }}">@csrf<button class="rounded-xl bg-[#052058] px-4 py-2 text-white">Create and verify backup</button></form></div>
<div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><tr><th>Created</th><th>Status</th><th>Path</th><th>Size</th><th>Verified</th><th></th></tr>@foreach($backups as $backup)<tr><td>{{ $backup->created_at }}</td><td>{{ $backup->status }}</td><td>{{ $backup->path }}</td><td>{{ $backup->size_bytes }}</td><td>{{ $backup->verified_at }}</td><td><form method="POST" action="{{ route('azari.admin.system-health.backup.verify',$backup) }}">@csrf<button>Verify</button></form></td></tr>@endforeach</table></div>{{ $backups->links() }}</section>
<section><h2 class="text-xl font-semibold">Provider status</h2>@foreach($providers as $provider)<div class="rounded-xl border p-3">{{ $provider->provider }} · {{ $provider->connection_status }} · {{ $provider->mode }}</div>@endforeach{{ $providers->links() }}</section>
<section><h2 class="text-xl font-semibold">Communication delivery</h2>@foreach($communications as $log)<div class="rounded-xl border p-3">{{ $log->channel }} · {{ $log->template }} · {{ $log->masked_recipient }} · {{ $log->status }}</div>@endforeach{{ $communications->links() }}</section>
<section><h2 class="text-xl font-semibold">Scheduled tasks</h2>@foreach($tasks as $task)<div class="rounded-xl border p-3">{{ $task->task }} · {{ $task->status }} · {{ $task->finished_at }}</div>@endforeach{{ $tasks->links() }}</section>
@if($failedJobs)
<section class="space-y-3">
    <h2 class="text-xl font-semibold">Failed jobs</h2>
    <p class="text-sm opacity-70">Only repeat-safe background jobs can be requeued here. Financial and booking jobs require independent reconciliation.</p>
    @forelse($failedJobs as $job)
        @php
            $jobPayload = json_decode((string) $job->payload, true);
            $jobName = (string) data_get($jobPayload, 'displayName', '');
            $commandName = (string) data_get($jobPayload, 'data.commandName', '');
            $safeRetry = in_array($jobName, \App\Http\Controllers\Admin\OperationsController::SAFE_RETRY_JOB_CLASSES, true)
                && $jobName === $commandName;
        @endphp
        <div class="rounded-xl border p-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="font-medium">Job #{{ $job->id }} · {{ $job->failed_at }}</div>
                <p class="text-xs opacity-70">{{ $safeRetry ? class_basename($jobName) : 'Manual technical review required' }}</p>
            </div>
            @if($safeRetry && auth()->user()?->isAdministrator() && auth()->user()->hasPermission('system-health.manage'))
                <form method="POST" action="{{ route('azari.admin.system-health.failed-jobs.retry', $job->id) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-[#052058] px-4 py-2 text-white hover:bg-[#0a3275]">Retry safely</button>
                </form>
            @endif
        </div>
    @empty
        <p class="text-sm opacity-70">No failed jobs recorded.</p>
    @endforelse
    {{ $failedJobs->links() }}
</section>
@endif
</div>
        
@endsection

    