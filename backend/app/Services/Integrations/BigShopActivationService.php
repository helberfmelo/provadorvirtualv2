<?php

namespace App\Services\Integrations;

use App\Models\IntegrationEvent;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\User;
use App\Models\WidgetInstall;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class BigShopActivationService
{
    public function activate(array $payload): array
    {
        $storeId = (string) data_get($payload, 'store_id');
        $storeName = (string) (data_get($payload, 'store_name') ?: 'Loja BigShop '.$storeId);
        $storeDomain = $this->storeDomain($payload);
        $email = (string) (data_get($payload, 'merchant.email') ?: data_get($payload, 'merchant_email'));

        if ($storeId === '' || $email === '') {
            throw new RuntimeException('Payload de ativação BigShop incompleto.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => data_get($payload, 'merchant.name') ?: data_get($payload, 'user.name') ?: $storeName,
                'role' => 'merchant',
                'password' => Hash::make(Str::random(32)),
            ]
        );

        $merchant = Merchant::query()->firstOrCreate(
            ['slug' => 'bigshop-'.$storeId],
            [
                'name' => data_get($payload, 'merchant.name') ?: $storeName,
                'billing_status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        $merchant->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'is_owner' => true],
        ]);

        $company = MerchantCompany::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'platform' => 'bigshop',
                'external_store_id' => $storeId,
            ],
            [
                'name' => $storeName,
                'domain' => $storeDomain,
                'status' => 'active',
            ]
        );

        $connection = PlatformConnection::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'platform' => 'bigshop',
            ],
            [
                'merchant_company_id' => $company->id,
                'external_store_id' => $storeId,
                'api_base_url' => data_get($payload, 'api_base_url') ?: 'https://api.bigshop.com.br',
                'access_token_encrypted' => filled(data_get($payload, 'access_token'))
                    ? Crypt::encryptString((string) data_get($payload, 'access_token'))
                    : null,
                'webhook_secret_encrypted' => filled(data_get($payload, 'webhook_secret'))
                    ? Crypt::encryptString((string) data_get($payload, 'webhook_secret'))
                    : null,
                'status' => filled(data_get($payload, 'access_token')) ? 'configured' : 'draft',
                'last_error' => null,
            ]
        );

        $widget = WidgetInstall::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
            ],
            [
                'merchant_company_id' => $company->id,
                'public_key' => WidgetInstall::query()->where('merchant_id', $merchant->id)->value('public_key')
                    ?: 'pv_bs_'.Str::lower(Str::random(18)),
                'platform' => 'bigshop',
                'allowed_domains' => array_values(array_filter([
                    $storeDomain,
                    'provadorvirtual.online',
                ])),
                'theme' => [
                    'primary' => '#0f172a',
                    'secondary' => '#ff4d5e',
                    'accent' => '#ff7a1a',
                    'button_style' => 'gradient',
                    'button_background' => '#ff4d5e',
                    'button_text' => '#ffffff',
                    'confetti_enabled' => true,
                    'presentation_mode' => 'drawer',
                ],
                'is_active' => true,
            ]
        );

        IntegrationEvent::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'platform_connection_id' => $connection->id,
            'platform' => 'bigshop',
            'event_type' => 'one_click_activation',
            'direction' => 'inbound',
            'status' => 'success',
            'summary' => [
                'contract_version' => $this->contractVersion(),
                'store_id' => $storeId,
                'store_domain' => $storeDomain,
                'has_access_token' => filled(data_get($payload, 'access_token')),
                'widget_public_key' => $widget->public_key,
                'install_widget' => (bool) data_get($payload, 'install_widget', true),
            ],
            'occurred_at' => now(),
        ]);

        $widgetUrl = url('/widget/v1/provador-virtual.js');

        return [
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'platform_connection_id' => $connection->id,
            'widget_public_key' => $widget->public_key,
            'dashboard_url' => url('/app/integracoes'),
            'widget_url' => $widgetUrl,
            'install_snippet' => $this->installSnippet($storeId, $widgetUrl, $widget->merchant_id),
            'integration_contract' => $this->contract($storeId, $widget, $widgetUrl),
            'status' => $connection->status,
        ];
    }

    private function storeDomain(array $payload): ?string
    {
        $domain = data_get($payload, 'store_domain');

        if (filled($domain)) {
            return (string) $domain;
        }

        $storeUrl = data_get($payload, 'store_url');

        if (! filled($storeUrl)) {
            return null;
        }

        return parse_url((string) $storeUrl, PHP_URL_HOST) ?: (string) $storeUrl;
    }

    private function contractVersion(): string
    {
        return '2026-05-23';
    }

    private function contract(string $storeId, WidgetInstall $widget, string $widgetUrl): array
    {
        return [
            'version' => $this->contractVersion(),
            'signature' => [
                'headers' => ['X-BigShop-Timestamp', 'X-BigShop-Signature'],
                'algorithm' => 'hmac_sha256(secret, timestamp + "." + raw_body)',
                'tolerance_seconds' => 600,
            ],
            'required_payload' => [
                'store_id',
                'store_name',
                'merchant.email ou merchant_email',
            ],
            'optional_payload' => [
                'store_domain',
                'store_url',
                'api_base_url',
                'access_token',
                'webhook_secret',
                'install_widget',
                'sync_after_activation',
                'callback_url',
            ],
            'widget' => [
                'platform' => 'bigshop',
                'public_key' => $widget->public_key,
                'script_url' => $widgetUrl,
                'container_id' => 'provador-virtual-container',
                'script_id' => 'provadorVirtualScript',
                'store_id' => $storeId,
                'product_id_attribute' => 'data-product-id',
                'variant_id_attribute' => 'data-variant-id',
                'sku_attribute' => 'data-sku',
            ],
        ];
    }

    private function installSnippet(string $storeId, string $widgetUrl, int $merchantId): string
    {
        return <<<HTML
<div id="provador-virtual-container"></div>
<script
  id="provadorVirtualScript"
  src="{$widgetUrl}"
  data-platform="bigshop"
  data-merchant-id="{$merchantId}"
  data-store-id="{$storeId}"
  data-product-id="BIGSHOP_PRODUCT_ID"
  data-variant-id="BIGSHOP_GRADE_ID"
  data-sku="BIGSHOP_SKU"
  data-container-id="provador-virtual-container"
  defer></script>
HTML;
    }
}
