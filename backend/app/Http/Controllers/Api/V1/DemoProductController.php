<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WidgetInstall;
use App\Support\WidgetPlacementCatalog;

class DemoProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->with(['company', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
            ->whereHas('company', fn ($query) => $query->where('external_store_id', 'pv-demo-store'))
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $widget = WidgetInstall::query()
            ->where('merchant_id', $products->first()?->merchant_id)
            ->where('merchant_company_id', $products->first()?->merchant_company_id)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'store' => [
                'name' => $products->first()?->company?->name ?? 'Provador Virtual Loja Teste',
                'platform' => $products->first()?->company?->platform ?? 'custom',
                'domain' => $products->first()?->company?->domain ?? 'provadorvirtual.online',
            ],
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'merchant_id' => $product->merchant_id,
                'store_id' => $product->merchant_company_id,
                'external_product_id' => $product->external_product_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'category' => $product->category,
                'gender' => $product->gender,
                'fit_profile' => $product->fit_profile,
                'image_url' => asset(ltrim($product->image_url ?? 'images/demo-product.jpg', '/')),
                'price_from' => $product->variants->min('price'),
                'sizes' => $product->variants->pluck('size_label')->values(),
            ])->values(),
            'widget' => [
                'public_key' => $widget?->public_key,
                'platform' => $widget?->platform ?? 'custom',
                'theme' => $widget?->theme ?? $this->defaultTheme(),
            ],
        ]);
    }

    public function show(?string $slug = null)
    {
        $product = Product::query()
            ->with(['company', 'measurementTable.rows', 'variants' => fn ($query) => $query->orderBy('id')])
            ->where('slug', $slug ?: 'vestido-midi-aurora')
            ->firstOrFail();

        $widget = WidgetInstall::query()
            ->where('merchant_id', $product->merchant_id)
            ->where('merchant_company_id', $product->merchant_company_id)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'merchant_id' => $product->merchant_id,
                'store_id' => $product->merchant_company_id,
                'external_product_id' => $product->external_product_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'category' => $product->category,
                'gender' => $product->gender,
                'fit_profile' => $product->fit_profile,
                'image_url' => asset(ltrim($product->image_url ?? 'images/demo-product.jpg', '/')),
                'company' => [
                    'id' => $product->company?->id,
                    'name' => $product->company?->name,
                    'platform' => $product->company?->platform,
                    'domain' => $product->company?->domain,
                ],
            ],
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'external_variant_id' => $variant->external_variant_id,
                'sku' => $variant->sku,
                'size_label' => $variant->size_label,
                'color' => $variant->color,
                'price' => $variant->price,
                'stock_quantity' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
            ])->values(),
            'measurement_table' => [
                'id' => $product->measurementTable?->id,
                'name' => $product->measurementTable?->name,
                'unit' => $product->measurementTable?->unit,
                'rows' => $product->measurementTable?->rows->map(fn ($row) => [
                    'size_label' => $row->size_label,
                    'bust' => [$row->bust_min, $row->bust_max],
                    'waist' => [$row->waist_min, $row->waist_max],
                    'hip' => [$row->hip_min, $row->hip_max],
                    'height' => [$row->height_min, $row->height_max],
                    'weight' => [$row->weight_min, $row->weight_max],
                ])->values() ?? [],
            ],
            'widget' => [
                'public_key' => $widget?->public_key,
                'platform' => $widget?->platform ?? 'custom',
                'theme' => $widget?->theme ?? $this->defaultTheme(),
            ],
        ]);
    }

    private function defaultTheme(): array
    {
        return [
            'primary' => '#0f172a',
            'secondary' => '#ff4d5e',
            'accent' => '#ff7a1a',
            'background' => '#ffffff',
            'text' => '#111827',
            'font_family' => 'Manrope, Inter, Arial, sans-serif',
            'font_size' => '14',
            'font_weight' => '800',
            'button_radius' => '8',
            'button_style' => 'gallery_1_text_icons',
            'button_background' => '#ff4d5e',
            'button_text' => '#ffffff',
            'button_primary_icon' => 'hanger',
            'button_secondary_icon' => 'ruler',
            'button_icon_animation' => true,
            'confetti_enabled' => true,
            'presentation_mode' => 'drawer',
            'placement' => WidgetPlacementCatalog::default(),
        ];
    }
}
