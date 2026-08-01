<?php
namespace Database\Seeders;
use App\Models\Permission;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
class AzariV105Seeder extends Seeder {
 public function run():void{
  foreach(['promotions','vouchers'] as $module)foreach(['view','create','edit','manage'] as $action)Permission::updateOrCreate(['slug'=>"$module.$action"],['name'=>ucwords($module).' '.ucfirst($action),'group'=>$module]);
  SiteSetting::put('contact_phone','+250799 643 143','text','customer_communications'); SiteSetting::put('whatsapp_number','+250799643143','text','customer_communications');
  $admin=User::whereRaw('LOWER(email)=?',[strtolower('admin@azariadmin.com')])->first();
  if($admin){$admin->forceFill(['account_type'=>'admin','staff_role'=>'administrator','is_admin'=>true,'is_active'=>true,'status'=>'active','suspended_at'=>null,'suspension_reason'=>null])->save();$ids=Permission::pluck('id');$sync=$ids->mapWithKeys(fn($id)=>[$id=>['granted_by'=>null]])->all();$admin->directPermissions()->sync($sync);}
 }
}
