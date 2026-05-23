<?php

namespace App\Support;

class PlatformCatalog
{
    public static function all(): array
    {
        return [
            'bigshop' => [
                'key' => 'bigshop',
                'name' => 'BigShop',
                'priority' => true,
                'icon' => 'fa-bolt',
                'install_mode' => 'one_click',
                'status' => 'draft',
            ],
            'shopify' => [
                'key' => 'shopify',
                'name' => 'Shopify',
                'priority' => false,
                'icon' => 'fa-bag-shopping',
                'install_mode' => 'manual',
                'status' => 'draft',
            ],
            'woocommerce' => [
                'key' => 'woocommerce',
                'name' => 'WooCommerce',
                'priority' => false,
                'icon' => 'fa-cart-shopping',
                'install_mode' => 'manual',
                'status' => 'draft',
            ],
            'nuvemshop' => [
                'key' => 'nuvemshop',
                'name' => 'Nuvemshop',
                'priority' => false,
                'icon' => 'fa-cloud',
                'install_mode' => 'manual',
                'status' => 'draft',
            ],
            'vtex' => [
                'key' => 'vtex',
                'name' => 'VTEX',
                'priority' => false,
                'icon' => 'fa-layer-group',
                'install_mode' => 'manual',
                'status' => 'draft',
            ],
            'tray' => [
                'key' => 'tray',
                'name' => 'Tray',
                'priority' => false,
                'icon' => 'fa-store',
                'install_mode' => 'manual',
                'status' => 'draft',
            ],
            'custom' => [
                'key' => 'custom',
                'name' => 'Personalizada',
                'priority' => false,
                'icon' => 'fa-code',
                'install_mode' => 'manual',
                'status' => 'draft',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function find(string $platform): ?array
    {
        return self::all()[$platform] ?? null;
    }
}
