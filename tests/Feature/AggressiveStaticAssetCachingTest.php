<?php

namespace Tests\Feature;

use Tests\TestCase;

class AggressiveStaticAssetCachingTest extends TestCase
{
    public function test_apache_policy_aggressively_caches_versioned_assets_and_public_images(): void
    {
        $rules = (string) file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('AZARI_AGGRESSIVE_STATIC_CACHE_V1', $rules);
        $this->assertStringContainsString('max-age=31536000, immutable', $rules);
        $this->assertStringContainsString('max-age=259200, stale-while-revalidate=604800, stale-if-error=2592000', $rules);
        $this->assertStringContainsString('Header merge Vary "Accept"', $rules);
        $this->assertStringContainsString('no-cache, private, must-revalidate', $rules);
        $this->assertStringContainsString('SetEnvIf Request_URI "^/build/assets/"', $rules);
        $this->assertStringContainsString('SetEnvIf Request_URI "^/fonts/"', $rules);
    }

    public function test_material_symbols_font_preserves_the_phase_eight_canonical_url(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'))
            ."\n".(string) file_get_contents(resource_path('css/legacy/app-legacy.css'));
        $preload = (string) file_get_contents(resource_path('views/partials/material-symbols-preload.blade.php'));
        $head = (string) file_get_contents(resource_path('views/partials/azari-head-assets.blade.php'));

        $canonicalSource = "src: url('/fonts/material-symbols-outlined.woff2') format('woff2');";

        $this->assertStringContainsString($canonicalSource, $css);
        $this->assertStringContainsString("asset('fonts/material-symbols-outlined.woff2')", $preload);
        $this->assertStringContainsString("asset('fonts/material-symbols-outlined.woff2')", $head);
        $this->assertStringNotContainsString('material-symbols-outlined.woff2?v=', $css);
        $this->assertStringNotContainsString('material-symbols-outlined.woff2?v=', $preload);
        $this->assertStringNotContainsString('material-symbols-outlined.woff2?v=', $head);
        $this->assertStringContainsString('fetchpriority="high"', $preload);
        $this->assertStringContainsString('font-display: block', $css);
        $this->assertStringContainsString('font-display: block', $head);
    }

    public function test_icons_remain_hidden_until_the_local_font_is_ready(): void
    {
        $head = (string) file_get_contents(resource_path('views/partials/azari-head-assets.blade.php'));
        $publicLayout = (string) file_get_contents(resource_path('views/components/public/layout.blade.php'));
        $userLayout = (string) file_get_contents(resource_path('views/layouts/user.blade.php'));

        $this->assertStringContainsString('html:not(.az-icons-ready) .material-symbols-outlined', $head);
        $this->assertStringContainsString('document.fonts.load', $head);
        $this->assertStringContainsString("root.classList.add('az-icons-local')", $head);
        $this->assertStringNotContainsString(
            "<script>document.documentElement.classList.add('az-icons-ready');</script>",
            $publicLayout
        );
        $this->assertStringNotContainsString('document.fonts.ready.finally', $userLayout);
    }
}
