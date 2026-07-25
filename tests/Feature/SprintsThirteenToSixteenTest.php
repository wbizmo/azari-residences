<?php
namespace Tests\Feature;
use App\Models\{Booking,Permission,SupportTicket,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
class SprintsThirteenToSixteenTest extends TestCase {
 use RefreshDatabase;
 public function test_required_routes_and_newsletter_absence():void{
  foreach(['user.support.index','user.support.attachment','azari.admin.support.index','azari.admin.reviews.index','azari.admin.promotions.index','azari.admin.reports.index','azari.admin.audit-logs.index','azari.admin.system-health.index'] as $route)$this->assertTrue(Route::has($route),$route);
  foreach(Route::getRoutes() as $route)$this->assertStringNotContainsString('newsletter',strtolower((string)$route->getName().' '.$route->uri()));
 }
 public function test_normal_user_cannot_discover_staff_routes():void{$user=User::factory()->create(['is_admin'=>false,'staff_role'=>null,'email_verified_at'=>now()]);$this->actingAs($user)->get(route('azari.admin.reports.index'))->assertNotFound();}
 public function test_staff_cannot_enter_customer_area():void{$staff=User::factory()->create(['is_admin'=>true,'email_verified_at'=>now()]);$this->actingAs($staff)->get(route('user.support.index'))->assertForbidden();}
 public function test_security_and_request_id_headers_exist():void{$response=$this->get('/');$response->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('X-Frame-Options','SAMEORIGIN');$this->assertNotEmpty($response->headers->get('X-Request-ID'));}
 public function test_permission_schema_uses_repository_group_column():void{$this->seed(\Database\Seeders\AzariFinalCompletionSeeder::class);$this->assertDatabaseHas('permissions',['slug'=>'reports.export','group'=>'reports']);}
 public function test_support_ticket_messages_can_be_private_internal_notes():void{$user=User::factory()->create(['email_verified_at'=>now()]);$ticket=SupportTicket::create(['reference'=>'AZR-SUP-TEST','user_id'=>$user->id,'category'=>'other','subject'=>'Test','status'=>'open','priority'=>'normal']);$ticket->messages()->create(['body'=>'guest','internal'=>false]);$ticket->messages()->create(['body'=>'secret','internal'=>true]);$this->assertCount(1,$ticket->publicMessages()->get());}
}
