<?php
namespace Tests\Feature;
use App\Models\Booking;
use App\Models\Permission;
use App\Models\Property;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Bookings\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AzariV105VoucherAndPermissionsTest extends TestCase {
 use RefreshDatabase;
 public function test_voucher_permissions_are_canonical():void{$this->seed(\Database\Seeders\AzariV105Seeder::class);foreach(['vouchers.view','vouchers.create','vouchers.edit','vouchers.manage','promotions.view'] as $slug)$this->assertTrue(Permission::where('slug',$slug)->exists(),$slug);}
 public function test_voucher_reduces_one_booking_total():void{$u=User::factory()->create();$p=Property::factory()->create();$b=Booking::factory()->create(['user_id'=>$u->id,'property_id'=>$p->id,'status'=>'pending_payment','subtotal'=>1000,'fee_total'=>0,'add_on_total'=>0,'tax_total'=>0,'total'=>1000,'guest_email'=>$u->email]);$v=Voucher::create(['code'=>'SAVE10','name'=>'Save ten','discount_type'=>'percentage','discount_value'=>10,'minimum_booking_value'=>0,'per_customer_limit'=>1,'is_active'=>true]);app(VoucherService::class)->apply($b,'save10');$this->assertSame('900.00',$b->fresh()->total);$this->assertSame('100.00',$b->fresh()->discount_total);$this->assertDatabaseCount('voucher_redemptions',1);}
}
