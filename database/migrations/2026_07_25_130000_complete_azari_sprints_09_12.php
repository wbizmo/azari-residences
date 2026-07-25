<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('bookings', function(Blueprint $t){
   if(!Schema::hasColumn('bookings','checked_out_at')) $t->timestamp('checked_out_at')->nullable();
   if(!Schema::hasColumn('bookings','no_show_at')) $t->timestamp('no_show_at')->nullable();
   if(!Schema::hasColumn('bookings','check_in_reversed_at')) $t->timestamp('check_in_reversed_at')->nullable();
  });
  Schema::create('stay_lifecycle_events', function(Blueprint $t){$t->id();$t->foreignId('booking_id')->constrained()->cascadeOnDelete();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('event');$t->text('note')->nullable();$t->string('viewer_timezone')->nullable();$t->string('operational_timezone')->default('Africa/Lagos');$t->string('ip_address',64)->nullable();$t->text('user_agent')->nullable();$t->timestamps();$t->index(['booking_id','created_at']);});
  Schema::create('service_requests', function(Blueprint $t){$t->id();$t->string('reference')->unique();$t->foreignId('booking_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();$t->string('type');$t->string('title');$t->string('status')->default('submitted');$t->dateTime('requested_at')->nullable();$t->json('details')->nullable();$t->text('notes')->nullable();$t->text('internal_notes')->nullable();$t->text('guest_reply')->nullable();$t->string('attachment_path')->nullable();$t->timestamps();$t->index(['status','type']);});
  Schema::create('service_request_events', function(Blueprint $t){$t->id();$t->foreignId('service_request_id')->constrained()->cascadeOnDelete();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('from_status')->nullable();$t->string('to_status');$t->text('note')->nullable();$t->boolean('guest_visible')->default(false);$t->timestamps();});
  Schema::create('communication_logs', function(Blueprint $t){$t->id();$t->string('channel');$t->string('template');$t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();$t->string('recipient');$t->string('masked_recipient')->nullable();$t->string('provider')->nullable();$t->string('provider_reference')->nullable();$t->string('status')->default('queued');$t->timestamp('queued_at')->nullable();$t->timestamp('sent_at')->nullable();$t->timestamp('delivered_at')->nullable();$t->timestamp('failed_at')->nullable();$t->text('safe_error')->nullable();$t->unsignedSmallInteger('retry_count')->default(0);$t->json('meta')->nullable();$t->timestamps();$t->index(['channel','status']);});
 }
 public function down(): void {Schema::dropIfExists('communication_logs');Schema::dropIfExists('service_request_events');Schema::dropIfExists('service_requests');Schema::dropIfExists('stay_lifecycle_events');}
};
