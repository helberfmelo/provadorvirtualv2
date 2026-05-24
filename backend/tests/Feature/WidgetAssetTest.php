<?php

namespace Tests\Feature;

use Tests\TestCase;

class WidgetAssetTest extends TestCase
{
    public function test_widget_assets_are_available_in_public_directory(): void
    {
        $script = public_path('widget/v1/provador-virtual.js');
        $css = public_path('widget/v1/provador-virtual.css');

        $this->assertFileExists($script);
        $this->assertFileExists($css);
        $scriptContents = file_get_contents($script);
        $cssContents = file_get_contents($css);

        $this->assertStringContainsString('data-pv-recommend', $scriptContents);
        $this->assertStringContainsString('widget_v2_staged', $scriptContents);
        $this->assertStringContainsString('triggerCelebration', $scriptContents);
        $this->assertStringContainsString('data-pv-send-feedback', $scriptContents);
        $this->assertStringContainsString("basePath + '/public/api/v1'", $scriptContents);
        $this->assertStringContainsString('diagnostics', $scriptContents);
        $this->assertStringContainsString('.pv-trigger', $cssContents);
        $this->assertStringContainsString('.pv-drawer', $cssContents);
        $this->assertStringContainsString('.pv-confetti-layer', $cssContents);
        $this->assertStringContainsString('.pv-debug', $cssContents);
    }
}
