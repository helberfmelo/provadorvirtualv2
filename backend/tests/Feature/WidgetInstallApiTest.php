<?php

namespace Tests\Feature;

use App\Models\MerchantCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WidgetInstallApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_install_requires_authentication_without_redirect_error(): void
    {
        $this->seed();

        $this->get('/api/v1/widget-install')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_merchant_can_view_and_update_widget_install(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->assertJsonPath('data.public_key', 'pv_demo_luna')
            ->assertJsonPath('data.platform', 'custom')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.theme.presentation_mode', 'drawer')
            ->assertJsonPath('data.theme.button_style', 'gallery_1_text_icons')
            ->assertJsonPath('data.theme.button_primary_icon', 'hanger')
            ->assertJsonPath('data.theme.button_secondary_icon', 'ruler')
            ->assertJsonPath('data.theme.button_icon_animation', true)
            ->assertJsonPath('data.theme.placement.mode', 'inside')
            ->assertJsonPath('data.theme.placement.selector', '#provador-virtual-container')
            ->assertJsonPath('data.draft.has_unpublished_changes', false)
            ->assertJsonPath('data.platform_guide.key', 'custom')
            ->assertJsonPath('data.platform_guide.guide.placement_label', 'Página de produto')
            ->assertJsonPath('data.platform_guide.guide.placement_suggestions.0.selector', '#provador-virtual-container')
            ->assertJsonPath('data.platform_guides.0.key', 'bigshop');

        $this->assertStringContainsString('provador-virtual.js', $response->json('data.snippet'));
        $this->assertStringContainsString('data-merchant-id', $response->json('data.snippet'));
        $this->assertStringContainsString('data-platform="custom"', $response->json('data.snippet'));
        $this->assertStringContainsString('placement', $response->json('data.snippet'));

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'mode' => 'draft',
                'platform' => 'bigshop',
                'allowed_domains' => ['Preview.Loja.Com.Br'],
                'theme' => [
                    'primary' => '#101820',
                    'presentation_mode' => 'modal',
                    'button_style' => 'gallery_10_badge_tooltip',
                    'button_primary_icon' => 'tape',
                    'button_secondary_icon' => 'chart',
                    'button_icon_animation' => false,
                ],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'custom')
            ->assertJsonPath('data.theme.primary', '#0f172a')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.draft.platform', 'bigshop')
            ->assertJsonPath('data.draft.allowed_domains.0', 'preview.loja.com.br')
            ->assertJsonPath('data.draft.theme.primary', '#101820')
            ->assertJsonPath('data.draft.theme.button_primary_icon', 'tape')
            ->assertJsonPath('data.draft.theme.button_secondary_icon', 'chart')
            ->assertJsonPath('data.draft.theme.button_icon_animation', false)
            ->assertJsonPath('data.draft.is_active', false)
            ->assertJsonPath('data.draft.has_unpublished_changes', true);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'mode' => 'discard',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'custom')
            ->assertJsonPath('data.draft.has_unpublished_changes', false);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'bigshop',
                'allowed_domains' => ['ProvadorVirtual.Online', 'localhost', 'localhost'],
                'theme' => [
                    'primary' => '#101820',
                    'secondary' => '#ff4d5e',
                    'accent' => '#17a398',
                    'presentation_mode' => 'modal',
                    'button_style' => 'gallery_12_dual_cards',
                    'button_background' => '#101820',
                    'button_text' => '#ffffff',
                    'button_primary_icon' => 'hanger',
                    'button_secondary_icon' => 'tape',
                    'button_icon_animation' => true,
                ],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.allowed_domains.0', 'provadorvirtual.online')
            ->assertJsonPath('data.allowed_domains.1', 'localhost')
            ->assertJsonPath('data.theme.primary', '#101820')
            ->assertJsonPath('data.theme.presentation_mode', 'modal')
            ->assertJsonPath('data.theme.button_style', 'gallery_12_dual_cards')
            ->assertJsonPath('data.theme.button_background', '#101820')
            ->assertJsonPath('data.theme.button_text', '#ffffff')
            ->assertJsonPath('data.theme.button_primary_icon', 'hanger')
            ->assertJsonPath('data.theme.button_secondary_icon', 'tape')
            ->assertJsonPath('data.theme.button_icon_animation', true)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.platform_guide.key', 'bigshop');

        $bigShopSnippet = $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->json('data.snippet');

        $this->assertStringContainsString('presentation_mode', $bigShopSnippet);
        $this->assertStringContainsString('button_style', $bigShopSnippet);
        $this->assertStringContainsString('button_background', $bigShopSnippet);
        $this->assertStringContainsString('button_primary_icon', $bigShopSnippet);
        $this->assertStringContainsString('button_secondary_icon', $bigShopSnippet);
        $this->assertStringContainsString('button_icon_animation', $bigShopSnippet);
        $this->assertStringContainsString('data-platform="bigshop"', $bigShopSnippet);
        $this->assertStringContainsString('BIGSHOP_PRODUCT_ID', $bigShopSnippet);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'mode' => 'draft',
                'platform' => 'shopify',
                'theme' => [
                    'primary' => '#333333',
                    'button_style' => 'gallery_5_pills',
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.draft.platform', 'shopify')
            ->assertJsonPath('data.draft.has_unpublished_changes', true);

        $shopifyResponse = $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'mode' => 'publish',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'shopify')
            ->assertJsonPath('data.theme.primary', '#333333')
            ->assertJsonPath('data.draft.has_unpublished_changes', false)
            ->assertJsonPath('data.platform_guide.key', 'shopify');

        $this->assertStringContainsString('{{ product.id }}', $shopifyResponse->json('data.snippet'));
        $this->assertStringContainsString('Template Liquid de produto', $shopifyResponse->json('data.platform_guide.guide.placement_label'));
    }

    public function test_bigshop_contract_keeps_widget_platform_locked(): void
    {
        $this->seed();

        MerchantCompany::query()
            ->where('external_store_id', 'pv-demo-store')
            ->update(['platform' => 'bigshop']);

        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.company.platform', 'bigshop');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'shopify',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sua empresa contratou o plano BigShop. O widget pode ser instalado apenas na BigShop.');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'bigshop',
                'allowed_domains' => ['provadorvirtual.online'],
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop');
    }

    public function test_merchant_can_preview_and_publish_widget_placement(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        Http::fake([
            'https://loja.test/produto-midi' => Http::response(
                '<html><body><form class="product-form"><div id="provador-virtual-container"></div><button type="submit">Comprar</button></form><script src="https://cdn.test/provador-virtual.js"></script></body></html>',
                200
            ),
        ]);

        $preview = $this->withHeaders($headers)
            ->postJson('/api/v1/widget-install/placement-preview', [
                'platform' => 'shopify',
                'url' => 'https://loja.test/produto-midi',
                'mode' => 'after',
                'selector' => '.product-form',
                'container_id' => 'provador-virtual-container',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'passed')
            ->assertJsonPath('data.placement.mode', 'after')
            ->assertJsonPath('data.diagnostics.anchor.matches', 1)
            ->assertJsonPath('data.diagnostics.container.matches', 1)
            ->assertJsonPath('data.diagnostics.container.before_script', true);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'mode' => 'publish',
                'platform' => 'shopify',
                'theme' => [
                    'placement' => [
                        'mode' => 'after',
                        'selector' => '.product-form',
                        'container_id' => 'provador-virtual-container',
                        'validation' => [
                            'status' => $preview->json('data.status'),
                            'url' => $preview->json('data.url'),
                            'checked_at' => $preview->json('data.checked_at'),
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'shopify')
            ->assertJsonPath('data.theme.placement.mode', 'after')
            ->assertJsonPath('data.theme.placement.selector', '.product-form')
            ->assertJsonPath('data.theme.placement.validation.status', 'passed');
    }

    public function test_invalid_or_failed_widget_placement_blocks_publish(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'mode' => 'publish',
                'theme' => [
                    'placement' => [
                        'mode' => 'inside',
                        'selector' => 'div[',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme.placement.selector');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'mode' => 'publish',
                'theme' => [
                    'placement' => [
                        'mode' => 'inside',
                        'selector' => '#provador-virtual-container',
                        'validation' => [
                            'status' => 'failed',
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme.placement.selector');
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
