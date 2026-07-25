<?php
namespace Database\Seeders;
use App\Models\{Permission,User};
use Illuminate\Database\Seeder;
class AzariSprintsNineToTwelveSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'documents.view','documents.manage','communications.view','communications.manage',
            'checkin.view','checkin.manage','service_requests.view','service_requests.manage',
        ] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => ucwords(str_replace(['.','_'], ' ', $slug))]);
        }
        $admin = User::query()->where('email', 'admin@azariadmin.com')->first();
        if ($admin) {
            $admin->forceFill(['account_type'=>'staff','staff_role'=>'administrator','is_admin'=>true,'is_active'=>true,'status'=>'active','suspended_at'=>null,'suspension_reason'=>null])->save();
            $admin->directPermissions()->sync(Permission::query()->pluck('id')->all());
        }
    }
}
