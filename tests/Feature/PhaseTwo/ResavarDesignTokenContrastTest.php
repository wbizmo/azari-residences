<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class ResavarDesignTokenContrastTest extends TestCase
{
    private function token(string $name): string
    {
        $css = file_get_contents(resource_path('css/reserva/tokens.css'));
        $pattern = '/--'.preg_quote($name, '/').':\\s*(#[0-9A-Fa-f]{6})\\s*;/';
        $this->assertSame(1, preg_match($pattern, $css, $match), 'Missing solid '.$name.' token.');

        return $match[1];
    }

    private function luminance(string $hex): float
    {
        $parts = [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
        $linear = array_map(static fn (float $part) =>
            $part <= 0.04045 ? $part / 12.92 : (($part + 0.055) / 1.055) ** 2.4,
            $parts
        );

        return $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
    }

    private function contrast(string $foreground, string $background): float
    {
        $high = max($this->luminance($foreground), $this->luminance($background));
        $low = min($this->luminance($foreground), $this->luminance($background));

        return ($high + 0.05) / ($low + 0.05);
    }

    public function test_brand_body_action_and_secondary_text_tokens_have_aa_contrast(): void
    {
        $white = $this->token('reserva-white');
        $navy = $this->token('reserva-navy');

        $this->assertGreaterThanOrEqual(4.5, $this->contrast($navy, $white));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->token('reserva-blue-text'), $white));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->token('reserva-orange-text'), $white));
    }

    public function test_keyboard_focus_indicator_has_at_least_three_to_one_contrast_on_white(): void
    {
        $this->assertGreaterThanOrEqual(3.0,
            $this->contrast($this->token('reserva-focus'), $this->token('reserva-white')));
        $this->assertStringContainsString(
            'outline-color: var(--reserva-white);',
            file_get_contents(resource_path('css/reserva/components.css'))
        );
    }
}
