<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog,BackupRun,CommunicationLog,PaymentProviderStatus,ScheduledTaskRun};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Artisan,DB,Log,Schema,Storage};
use Illuminate\View\View;
class OperationsController {
 public function index():View{
  $health=['application_environment'=>config('app.env'),'debug_enabled'=>(bool)config('app.debug'),'operational_timezone'=>config('azari.timezone','Africa/Lagos'),'database'=>$this->check(fn()=>DB::select('select 1')),'cache'=>$this->check(fn()=>cache()->put('azari-health',true,10)),'private_storage'=>$this->check(fn()=>Storage::disk('private')->put('healthcheck.tmp','ok')),'queue_connection'=>config('queue.default'),'scheduler_last_run'=>ScheduledTaskRun::latest('finished_at')->first()?->finished_at,'failed_jobs'=>Schema::hasTable('failed_jobs')?DB::table('failed_jobs')->count():0,'latest_log'=>$this->logSummary()];
  return view('admin.operations.index',['health'=>$health,'providers'=>PaymentProviderStatus::latest()->paginate(10,['*'],'providers')->withQueryString(),'communications'=>CommunicationLog::latest()->paginate(10,['*'],'communications')->withQueryString(),'tasks'=>ScheduledTaskRun::latest()->paginate(10,['*'],'tasks')->withQueryString(),'failedJobs'=>Schema::hasTable('failed_jobs')?DB::table('failed_jobs')->latest('id')->paginate(10,['*'],'failed_jobs')->withQueryString():null,'backups'=>BackupRun::latest()->paginate(10,['*'],'backups')->withQueryString()]);
 }
 public function audit():View{return view('admin.audit.index',['logs'=>AuditLog::with('actor')->latest()->paginate(10)->withQueryString()]);}
 public function backup():RedirectResponse{try{Artisan::call('azari:backup',['--verify'=>true]);return back()->with('success',trim(Artisan::output())?:'Backup completed.');}catch(\Throwable $e){report($e);return back()->with('error','Backup could not be completed. Review server logs.');}}
 public function verifyBackup(BackupRun $backupRun):RedirectResponse{try{$ok=$backupRun->path&&Storage::disk('local')->exists($backupRun->path)&&hash_equals((string)$backupRun->checksum,hash_file('sha256',Storage::disk('local')->path($backupRun->path)));$backupRun->update(['status'=>$ok?'verified':'verification_failed','verified_at'=>$ok?now():null]);AuditLog::record('backup.verified',$backupRun,[],['status'=>$backupRun->status]);return back()->with($ok?'success':'error',$ok?'Backup checksum verified.':'Backup verification failed.');}catch(\Throwable $e){report($e);return back()->with('error','Backup verification failed safely.');}}
 private function check(callable $fn):string{try{$fn();return 'healthy';}catch(\Throwable $e){Log::warning('Azari health check failed',['request_id'=>request()?->attributes->get('request_id'),'exception'=>get_class($e),'message'=>$e->getMessage()]);return 'unavailable';}}
 private function logSummary():array{$file=storage_path('logs/laravel.log');if(!is_file($file))return ['status'=>'no_log_file'];$size=filesize($file)?:0;$tail='';$fh=fopen($file,'rb');if($fh){fseek($fh,max(0,$size-16384));$tail=stream_get_contents($fh)?:'';fclose($fh);}return ['size_bytes'=>$size,'errors'=>substr_count($tail,'.ERROR:'),'warnings'=>substr_count($tail,'.WARNING:'),'last_modified'=>date(DATE_ATOM,filemtime($file)?:time())];}
}
