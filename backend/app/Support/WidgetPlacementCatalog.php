<?php

namespace App\Support;

class WidgetPlacementCatalog
{
    public const DEFAULT_CONTAINER_ID = 'provador-virtual-container';

    public const DEFAULT_MODE = 'inside';

    public static function modes(): array
    {
        return ['inside', 'after', 'before'];
    }

    public static function default(): array
    {
        return [
            'mode' => self::DEFAULT_MODE,
            'selector' => '#'.self::DEFAULT_CONTAINER_ID,
            'container_id' => self::DEFAULT_CONTAINER_ID,
            'validation' => [
                'status' => 'untested',
            ],
        ];
    }

    public static function normalize(?array $placement): array
    {
        $placement = $placement ?? [];
        $default = self::default();

        $mode = in_array($placement['mode'] ?? null, self::modes(), true)
            ? $placement['mode']
            : $default['mode'];

        $selector = trim((string) ($placement['selector'] ?? $default['selector']));
        $containerId = trim((string) ($placement['container_id'] ?? $default['container_id']));

        $normalized = [
            'mode' => $mode,
            'selector' => $selector !== '' ? $selector : $default['selector'],
            'container_id' => preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/', $containerId)
                ? $containerId
                : $default['container_id'],
        ];

        $validation = $placement['validation'] ?? null;

        if (is_array($validation)) {
            $status = in_array($validation['status'] ?? null, ['untested', 'passed', 'warning', 'failed'], true)
                ? $validation['status']
                : 'untested';

            $normalized['validation'] = array_filter([
                'status' => $status,
                'url' => isset($validation['url']) ? trim((string) $validation['url']) : null,
                'checked_at' => isset($validation['checked_at']) ? trim((string) $validation['checked_at']) : null,
                'message' => isset($validation['message']) ? trim((string) $validation['message']) : null,
            ], fn ($value): bool => ! ($value === null || $value === ''));
        } else {
            $normalized['validation'] = $default['validation'];
        }

        return $normalized;
    }

    public static function suggestions(string $platform): array
    {
        $common = [
            ['selector' => '#provador-virtual-container', 'mode' => 'inside', 'label' => 'Container padrão'],
            ['selector' => '.product-form', 'mode' => 'after', 'label' => 'Depois do formulário'],
            ['selector' => 'button[type="submit"]', 'mode' => 'before', 'label' => 'Antes do comprar'],
        ];

        $platformSuggestions = match ($platform) {
            'bigshop' => [
                ['selector' => '.product-info__buy', 'mode' => 'before', 'label' => 'Antes da compra BigShop'],
                ['selector' => '.product-sku-selector', 'mode' => 'after', 'label' => 'Depois da grade BigShop'],
                ['selector' => '[data-product-variants]', 'mode' => 'after', 'label' => 'Depois das variações'],
            ],
            'shopify' => [
                ['selector' => 'variant-radios', 'mode' => 'after', 'label' => 'Depois das variantes'],
                ['selector' => 'product-form', 'mode' => 'before', 'label' => 'Antes do formulário Liquid'],
                ['selector' => '.product-form__buttons', 'mode' => 'before', 'label' => 'Antes dos botões Shopify'],
            ],
            'woocommerce' => [
                ['selector' => 'form.cart', 'mode' => 'before', 'label' => 'Antes do carrinho Woo'],
                ['selector' => '.variations_form', 'mode' => 'after', 'label' => 'Depois das variações'],
                ['selector' => '.single_add_to_cart_button', 'mode' => 'before', 'label' => 'Antes do comprar Woo'],
            ],
            'nuvemshop' => [
                ['selector' => '.js-product-variants', 'mode' => 'after', 'label' => 'Depois das variantes'],
                ['selector' => '.js-addtocart', 'mode' => 'before', 'label' => 'Antes do comprar'],
                ['selector' => '.product-detail-form', 'mode' => 'inside', 'label' => 'Dentro do formulário'],
            ],
            'vtex' => [
                ['selector' => '.vtex-flex-layout-0-x-flexCol--buy-box', 'mode' => 'inside', 'label' => 'Buy box VTEX'],
                ['selector' => '.vtex-product-summary-2-x-buyButton', 'mode' => 'before', 'label' => 'Antes do comprar VTEX'],
                ['selector' => '[data-testid="sku-selector"]', 'mode' => 'after', 'label' => 'Depois do SKU selector'],
            ],
            'tray' => [
                ['selector' => '.product-variations', 'mode' => 'after', 'label' => 'Depois das variações'],
                ['selector' => '#button-buy', 'mode' => 'before', 'label' => 'Antes do comprar Tray'],
                ['selector' => '.product-form', 'mode' => 'inside', 'label' => 'Formulário Tray'],
            ],
            'loja_integrada' => [
                ['selector' => '.acoes-produto', 'mode' => 'before', 'label' => 'Antes das ações'],
                ['selector' => '.atributos', 'mode' => 'after', 'label' => 'Depois dos atributos'],
                ['selector' => '#form-comprar', 'mode' => 'inside', 'label' => 'Dentro do formulário'],
            ],
            'magento' => [
                ['selector' => '#product_addtocart_form', 'mode' => 'inside', 'label' => 'Formulário Magento'],
                ['selector' => '.box-tocart', 'mode' => 'before', 'label' => 'Antes do carrinho'],
                ['selector' => '.swatch-attribute', 'mode' => 'after', 'label' => 'Depois dos swatches'],
            ],
            'opencart' => [
                ['selector' => '#product', 'mode' => 'inside', 'label' => 'Bloco product.twig'],
                ['selector' => '#button-cart', 'mode' => 'before', 'label' => 'Antes do carrinho'],
                ['selector' => '.form-group.required', 'mode' => 'after', 'label' => 'Depois das opções'],
            ],
            'xml_feed' => [
                ['selector' => '.product-options', 'mode' => 'after', 'label' => 'Depois das opções'],
                ['selector' => '.buy-box', 'mode' => 'inside', 'label' => 'Buy box do tema'],
                ['selector' => '[data-sku]', 'mode' => 'after', 'label' => 'Depois do SKU do feed'],
            ],
            'api' => [
                ['selector' => '[data-product-form]', 'mode' => 'inside', 'label' => 'Formulário da API'],
                ['selector' => '[data-buy-button]', 'mode' => 'before', 'label' => 'Antes do comprar'],
                ['selector' => '[data-variant-selector]', 'mode' => 'after', 'label' => 'Depois da variação'],
            ],
            default => [],
        };

        return collect([...$platformSuggestions, ...$common])
            ->unique(fn (array $suggestion): string => $suggestion['mode'].'|'.$suggestion['selector'])
            ->values()
            ->all();
    }
}
