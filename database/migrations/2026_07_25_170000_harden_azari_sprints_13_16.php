<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  if(Schema::hasTable('support_tickets')) Schema::table('support_tickets',function(Blueprint $t){
   if(!Schema::hasColumn('support_tickets','first_responded_at'))$t->timestamp('first_responded_at')->nullable();
   if(!Schema::hasColumn('support_tickets','response_due_at'))$t->timestamp('response_due_at')->nullable();
   if(!Schema::hasColumn('support_tickets','resolution_note'))$t->text('resolution_note')->nullable();
  });
  if(!Schema::hasTable('backup_runs')) Schema::create('backup_runs',function(Blueprint $t){$t->id();$t->string('driver');$t->string('path')->nullable();$t->string('status')->default('started');$t->unsignedBigInteger('size_bytes')->nullable();$t->string('checksum')->nullable();$t->timestamp('started_at');$t->timestamp('finished_at')->nullable();$t->timestamp('verified_at')->nullable();$t->text('safe_error')->nullable();$t->json('metadata')->nullable();$t->timestamps();$t->index(['status','created_at']);});
 }
 public function down():void{Schema::dropIfExists('backup_runs');}
};
