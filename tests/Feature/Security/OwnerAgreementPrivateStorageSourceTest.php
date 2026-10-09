<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\TestCase;

class OwnerAgreementPrivateStorageSourceTest extends TestCase
{
    public function test_admin_and_owner_agreement_downloads_use_exact_same_private_disk_path(): void
    {
        $root = dirname(__DIR__, 3);
        $owner = file_get_contents($root.'/app/Http/Controllers/User/PropertyOwnerController.php');
        $admin = file_get_contents($root.'/app/Http/Controllers/Admin/OwnerMarketplaceController.php');

        foreach ([$owner, $admin] as $source) {
            $this->assertStringContainsString("Storage::disk('private')", $source);
            $this->assertStringContainsString("'agreements/'.", $source);
            $this->assertStringNotContainsString("makeDirectory('private/agreements')", $source);
        }
        $this->assertStringContainsString('abort_unless($agreement !== null, 404)', $admin);
    }
}
