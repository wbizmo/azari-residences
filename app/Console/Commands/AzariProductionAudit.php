<?php
namespace App\Console\Commands;
use App\Http\Controllers\Admin\ReportController;
use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Route,Schema,Storage};
class AzariProductionAudit extends Command {
 protected $signature='azari:production-audit {--strict : Fail for environment-only production requirements}'; protected $description='Run the canonical Azari production readiness gate';
 public function handle():int{
  $requiredRoutes=['user.dashboard','user.support.index','user.support.attachment','azari.admin.support.index','azari.admin.reviews.index','azari.admin.promotions.index','azari.admin.reports.index','azari.admin.audit-logs.index','azari.admin.system-health.index'];
  $checks=['Application key present'=>filled(config('app.key')),'Database reachable'=>$this->safe(fn()=>DB::select('select 1')),'Private storage writable'=>$this->safe(fn()=>Storage::disk('private')->put('audit.tmp','ok')),'Operational timezone configured'=>config('azari.timezone','Africa/Lagos')==='Africa/Lagos','Support schema'=>Schema::hasTable('support_tickets')&&Schema::hasTable('support_ticket_messages'),'Reviews schema'=>Schema::hasTable('reviews'),'Promotions schema'=>Schema::hasTable('promotions'),'Backup history schema'=>Schema::hasTable('backup_runs'),'All report families registered'=>count(ReportController::TYPES)===19,'Required protected routes'=>collect($requiredRoutes)->every(fn($r)=>Route::has($r)),'Newsletter route absent'=>collect(Route::getRoutes())->every(fn($route)=>!str_contains(strtolower((string)$route->getName().' '.$route->uri()),'newsletter'))];
  $environment=['APP_DEBUG disabled'=>!config('app.debug'),'Production environment'=>config('app.env')==='production','Asynchronous queue'=>config('queue.default')!=='sync','Non-log mail transport'=>config('mail.default')!=='log','Recent verified backup'=>Schema::hasTable('backup_runs')&&BackupRun::where('status','verified')->where('verified_at','>=',now()->subDays(7))->exists()];
  $failed=false;foreach($checks as $label=>$ok){$this->line(($ok?'[PASS] ':'[FAIL] ').$label);$failed|=!$ok;}foreach($environment as $label=>$ok){$this->line(($ok?'[PASS] ':'[ENV ] ').$label);if($this->option('strict'))$failed|=!$ok;}return $failed?self::FAILURE:self::SUCCESS;
 }
 private function safe(callable $f):bool{try{$f();return true;}catch(\Throwable){return false;}}
}
