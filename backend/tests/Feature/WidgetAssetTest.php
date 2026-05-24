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
        $this->assertStringContainsString('data-pv-footer-action', $scriptContents);
        $this->assertStringContainsString('data-pv-step', $scriptContents);
        $this->assertStringContainsString('data-pv-final', $scriptContents);
        $this->assertStringContainsString('Seu tamanho &eacute;', $scriptContents);
        $this->assertStringContainsString('Aumentar precis&atilde;o', $scriptContents);
        $this->assertStringContainsString('scheduleAutoRecommendation', $scriptContents);
        $this->assertStringContainsString('pv_shopper_profile_v2_table_', $scriptContents);
        $this->assertStringContainsString('confetti_enabled', $scriptContents);
        $this->assertStringContainsString('widget_v2_staged', $scriptContents);
        $this->assertStringContainsString('v2_sprint_68', $scriptContents);
        $this->assertStringContainsString('triggerCelebration', $scriptContents);
        $this->assertStringContainsString('data-pv-send-feedback', $scriptContents);
        $this->assertStringContainsString('assetBaseUrl', $scriptContents);
        $this->assertStringContainsString('pv-main-button-subtle', $scriptContents);
        $this->assertStringContainsString('Nota da recomenda&ccedil;&atilde;o', $scriptContents);
        $this->assertStringContainsString('pv-shape-image', $scriptContents);
        $this->assertStringContainsString("basePath + '/public/api/v1'", $scriptContents);
        $this->assertStringContainsString('diagnostics', $scriptContents);
        $this->assertStringContainsString('browserStorageNoticeHtml', $scriptContents);
        $this->assertStringContainsString('Ao usar o Provador Virtual', $scriptContents);
        $this->assertStringNotContainsString('Salvar minhas medidas neste navegador para pr&oacute;ximas recomenda&ccedil;&otilde;es.', $scriptContents);
        $this->assertStringContainsString('.pv-trigger', $cssContents);
        $this->assertStringContainsString('.pv-drawer', $cssContents);
        $this->assertStringContainsString('.pv-confetti-layer', $cssContents);
        $this->assertStringContainsString('.pv-recommendation-inline', $cssContents);
        $this->assertStringContainsString('.pv-stepper button', $cssContents);
        $this->assertStringContainsString('.pv-debug', $cssContents);
        $this->assertStringContainsString('.pv-shape-image', $cssContents);
        $this->assertStringContainsString('-webkit-mask', $cssContents);
        $this->assertStringContainsString('.pv-main-button-subtle', $cssContents);
        $this->assertStringContainsString('.pv-browser-note', $cssContents);
        $this->assertStringContainsString('font-style: italic;', $cssContents);

        foreach ([
            'retangular.png',
            'triangulo.png',
            'triangulo_invertido.png',
            'oval.png',
            'ampulheta.png',
            'masc_retangular.png',
            'masc_triangulo.png',
            'masc_tri_invertido.png',
            'masc_oval.png',
        ] as $asset) {
            $this->assertFileExists(public_path('widget/v1/assets/body-shapes/'.$asset));
        }
    }
}
