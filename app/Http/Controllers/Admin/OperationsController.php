<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog,BackupRun,CommunicationLog,PaymentProviderStatus,ScheduledTaskRun,SystemHeartbeat};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan,Cache,DB,Log,Schema,Storage};
use Illuminate\View\View;
class OperationsController {
 public const SAFE_RETRY_JOB_CLASSES = [
  \App\Jobs\RecordQueueHeartbeat::class,
  \App\Jobs\SyncChannelConnection::class,
  \App\Jobs\GenerateResponsiveImageDerivatives::class,
 ];
 public function index():View{
  $health=['application_environment'=>config('app.env'),'debug_enabled'=>(bool)config('app.debug'),'operational_timezone'=>config('localization.platform_timezone','UTC'),'database'=>$this->check(fn()=>DB::select('select 1')),'cache'=>$this->check(fn()=>cache()->put('azari-health',true,10)),'private_storage'=>$this->check(fn()=>Storage::disk('private')->put('healthcheck.tmp','ok')),'queue_connection'=>config('queue.default'),'scheduler_last_run'=>ScheduledTaskRun::latest('finished_at')->first()?->finished_at,'scheduler_heartbeat'=>Schema::hasTable('system_heartbeats')?SystemHeartbeat::where('component','scheduler')->value('last_seen_at'):null,'queue_heartbeat'=>Schema::hasTable('system_heartbeats')?SystemHeartbeat::where('component','queue')->value('last_seen_at'):null,'failed_jobs'=>Schema::hasTable('failed_jobs')?DB::table('failed_jobs')->count():0,'latest_log'=>$this->logSummary()];
  return view('admin.operations.index',['health'=>$health,'providers'=>PaymentProviderStatus::latest()->paginate(10,['*'],'providers')->withQueryString(),'communications'=>CommunicationLog::latest()->paginate(10,['*'],'communications')->withQueryString(),'tasks'=>ScheduledTaskRun::latest()->paginate(10,['*'],'tasks')->withQueryString(),'failedJobs'=>Schema::hasTable('failed_jobs')?DB::table('failed_jobs')->latest('id')->paginate(10,['*'],'failed_jobs')->withQueryString():null,'backups'=>BackupRun::latest()->paginate(10,['*'],'backups')->withQueryString()]);
 }
 public function audit():View{return view('admin.audit.index',['logs'=>AuditLog::with('actor')->latest()->paginate(10)->withQueryString()]);}
 public function backup():RedirectResponse{try{$code=Artisan::call('azari:backup',['--verify'=>true]);if($code!==0){return back()->with('error','Backup verification failed. Review protected server logs.');}return back()->with('success','Encrypted backup created and structurally verified. An isolated restore drill is still required.');}catch(\Throwable $e){report($e);return back()->with('error','Backup could not be completed. Review server logs.');}}
 public function verifyBackup(BackupRun $backupRun):RedirectResponse{try{$code=Artisan::call('azari:backup-restore-check',['backupRun'=>$backupRun->getKey()]);$ok=$code===0;$backupRun->forceFill(['status'=>$ok?'verified':'verification_failed','verified_at'=>$ok?now():null])->save();AuditLog::record('backup.verified',$backupRun,[],['status'=>$backupRun->status,'archive_integrity'=>$ok]);return back()->with($ok?'success':'error',$ok?'Encrypted backup integrity, decryption and table structure verified. An isolated restore drill is still required.':'Backup verification failed. Review protected server logs.');}catch(\Throwable $e){report($e);return back()->with('error','Backup verification failed safely.');}}
 public function retryFailedJob(Request $request, int $id):RedirectResponse
 {
  abort_unless($request->user()?->isAdministrator()
   && $request->user()->hasPermission('system-health.manage'), 403);
  abort_unless(Schema::hasTable('failed_jobs'), 404);
  $lock = Cache::lock('resavar:retry-failed-job:'.$id, 30);
  if (! $lock->get()) {
   return back()->with('warning', 'This failed job is already being reviewed for retry.');
  }
  try {
   $job = DB::table('failed_jobs')->where('id', $id)->first();
   abort_unless($job !== null, 404);
   $payload = json_decode((string) $job->payload, true);
   $name = (string) data_get($payload, 'displayName', '');
   $commandName = (string) data_get($payload, 'data.commandName', '');
   if (! in_array($name, self::SAFE_RETRY_JOB_CLASSES, true)
    || $commandName !== $name || blank($job->uuid)) {
    return back()->with('error', 'This failed job cannot be safely retried from the dashboard. Escalate for review.');
   }

   // queue:retry requeues the known idempotent job via Laravel's failed job
   // provider, rather than unserializing untrusted payload in this request.
   $result = Artisan::call('queue:retry', ['id' => [(string) $job->uuid]]);
   if ($result !== 0 || DB::table('failed_jobs')->where('id', $id)->exists()) {
    return back()->with('error', 'Job could not be requeued. Review protected queue logs.');
   }

   AuditLog::record('operations.failed_job_requeued', null, [], [
    'job_class' => $name, 'failed_job_id' => $id,
   ], actorId: $request->user()->getKey());
   return back()->with('success', 'Approved background job queued for a controlled retry.');
  } catch (\Throwable $error) {
   Log::warning('Failed-job retry was not completed.', [
    'failed_job_id' => $id, 'failure_class' => $error::class,
   ]);
   return back()->with('error', 'Job could not be requeued. Review protected queue logs.');
  } finally {
   $lock->release();
  }
 }
 private function check(callable $fn):string{try{$fn();return 'healthy';}catch(\Throwable $e){Log::warning('Azari health check failed',['request_id'=>request()?->attributes->get('request_id'),'exception'=>get_class($e),'message'=>$e->getMessage()]);return 'unavailable';}}
 private function logSummary():array{$file=storage_path('logs/laravel.log');if(!is_file($file))return ['status'=>'no_log_file'];$size=filesize($file)?:0;$tail='';$fh=fopen($file,'rb');if($fh){fseek($fh,max(0,$size-16384));$tail=stream_get_contents($fh)?:'';fclose($fh);}return ['size_bytes'=>$size,'errors'=>substr_count($tail,'.ERROR:'),'warnings'=>substr_count($tail,'.WARNING:'),'last_modified'=>date(DATE_ATOM,filemtime($file)?:time())];}
}
