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
        $this->assertStringContainsString('data-pv-submit', file_get_contents($script));
        $this->assertStringContainsString("basePath + '/public/api/v1'", file_get_contents($script));
        $this->assertStringContainsString('diagnostics', file_get_contents($script));
        $this->assertStringContainsString('.pv-trigger', file_get_contents($css));
        $this->assertStringContainsString('.pv-debug', file_get_contents($css));
    }
}
