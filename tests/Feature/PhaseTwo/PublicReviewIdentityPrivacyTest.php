<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class PublicReviewIdentityPrivacyTest extends TestCase
{
    public function test_public_review_cards_use_pseudonyms_not_account_legal_names(): void
    {
        foreach ([
            resource_path('views/public/properties/show.blade.php'),
            resource_path('views/public/properties/reviews.blade.php'),
        ] as $view) {
            $html = file_get_contents($view);
            $this->assertStringContainsString('Verified guest', $html);
            $this->assertStringNotContainsString('$review->user->name', $html);
        }

        $directory = file_get_contents(app_path(
            'Http/Controllers/PublicSite/PublicPropertyReviewsController.php'
        ));
        $this->assertStringNotContainsString("->with(['user:id,name'])", $directory);
    }
}
