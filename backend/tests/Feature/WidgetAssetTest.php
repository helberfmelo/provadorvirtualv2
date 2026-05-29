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
        $this->assertStringContainsString('Usar tamanho', $scriptContents);
        $this->assertStringContainsString('provadorvirtual:size-selected', $scriptContents);
        $this->assertStringContainsString('data-pv-select-recommended-size', $scriptContents);
        $this->assertStringContainsString('suppressDrawerOpenUntil', $scriptContents);
        $this->assertStringContainsString('closeBackdrop(backdrop)', $scriptContents);
        $this->assertStringContainsString('Aumentar precis&atilde;o', $scriptContents);
        $this->assertStringContainsString('scheduleAutoRecommendation', $scriptContents);
        $this->assertStringContainsString('pv_shopper_profile_v2_table_', $scriptContents);
        $this->assertStringContainsString('confetti_enabled', $scriptContents);
        $this->assertStringContainsString('presentation_mode', $scriptContents);
        $this->assertStringContainsString('button_style', $scriptContents);
        $this->assertStringContainsString('button_background', $scriptContents);
        $this->assertStringContainsString('button_text', $scriptContents);
        $this->assertStringContainsString('button_primary_icon', $scriptContents);
        $this->assertStringContainsString('button_secondary_icon', $scriptContents);
        $this->assertStringContainsString('button_icon_animation', $scriptContents);
        $this->assertStringContainsString('placementConfig', $scriptContents);
        $this->assertStringContainsString('resolvePlacementTarget', $scriptContents);
        $this->assertStringContainsString('placeContainer', $scriptContents);
        $this->assertStringContainsString('data-pv-root', $scriptContents);
        $this->assertStringContainsString('buttonStyle', $scriptContents);
        $this->assertStringContainsString('buttonIconSvg', $scriptContents);
        $this->assertStringContainsString('buttonIconAnimationEnabled', $scriptContents);
        $this->assertStringContainsString('presentationMode', $scriptContents);
        $this->assertStringContainsString('pv-recommendation-modal', $scriptContents);
        $this->assertStringContainsString('widget_v2_staged', $scriptContents);
        $this->assertStringContainsString('v2_sprint_68', $scriptContents);
        $this->assertStringContainsString('triggerCelebration', $scriptContents);
        $this->assertStringContainsString('data-pv-send-feedback', $scriptContents);
        $this->assertStringContainsString('assetBaseUrl', $scriptContents);
        $this->assertStringContainsString('pv-main-button-subtle', $scriptContents);
        $this->assertStringContainsString('pv-shape-image', $scriptContents);
        $this->assertStringContainsString("basePath + '/public/api/v1'", $scriptContents);
        $this->assertStringContainsString('diagnostics', $scriptContents);
        $this->assertStringContainsString('browserStorageNoticeHtml', $scriptContents);
        $this->assertStringContainsString('Ao usar o Provador Virtual', $scriptContents);
        $this->assertMatchesRegularExpression('/state\.step === 1\)\s*\{\s*html \+= stepOneHtml\(\);\s*html \+= browserStorageNoticeHtml\(\);/', $scriptContents);
        $this->assertStringNotContainsString('Nota da recomenda&ccedil;&atilde;o', $scriptContents);
        $this->assertStringNotContainsString('data-pv-rating', $scriptContents);
        $this->assertStringNotContainsString('Salvar minhas medidas neste navegador para pr&oacute;ximas recomenda&ccedil;&otilde;es.', $scriptContents);
        $this->assertStringContainsString('<img class="pv-shape-image"', $scriptContents);
        $this->assertStringContainsString('loading="eager"', $scriptContents);
        $this->assertStringContainsString('.pv-trigger', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_1_text_icons', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_2_side_icons', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_3_dark_outline', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_4_underlined_icons', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_5_pills', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_6_split_line', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_7_editorial_links', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_8_dotted_stack', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_9_light_block', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_10_badge_tooltip', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_11_icon_chips', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-gallery_12_dual_cards', $cssContents);
        $this->assertStringContainsString('.pv-trigger-icon-animated', $cssContents);
        $this->assertStringContainsString('.pv-trigger-icon svg', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-clean', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-outline', $cssContents);
        $this->assertStringContainsString('.pv-trigger-style-soft', $cssContents);
        $this->assertStringContainsString('.pv-drawer', $cssContents);
        $this->assertStringContainsString('.pv-recommendation-modal', $cssContents);
        $this->assertStringContainsString('.pv-recommendation-modal-backdrop', $cssContents);
        $this->assertStringContainsString('.pv-confetti-layer', $cssContents);
        $this->assertStringContainsString('.pv-recommendation-inline', $cssContents);
        $this->assertStringContainsString('.pv-stepper button', $cssContents);
        $this->assertStringContainsString('.pv-debug', $cssContents);
        $this->assertStringContainsString('.pv-shape-image', $cssContents);
        $this->assertStringNotContainsString('.pv-rating', $cssContents);
        $this->assertStringContainsString('object-fit: contain;', $cssContents);
        $this->assertStringContainsString('.pv-main-button-subtle', $cssContents);
        $this->assertStringContainsString('.pv-result-size', $cssContents);
        $this->assertStringContainsString('.pv-inline-size-button', $cssContents);
        $this->assertStringContainsString('.pv-browser-note', $cssContents);
        $this->assertStringContainsString('font-size: 11px;', $cssContents);
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
