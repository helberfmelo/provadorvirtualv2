<?php

namespace Database\Seeders;

use App\Models\FitProfile;
use App\Models\MeasurementTable;
use App\Models\MeasurementTableRow;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\MerchantOrder;
use App\Models\MerchantReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Models\WidgetInstall;
use App\Support\WidgetModalCatalog;
use App\Support\WidgetPlacementCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'demo@provadorvirtual.online'],
            [
                'name' => 'Lojista Demo',
                'role' => 'merchant',
                'password' => Hash::make('provador123'),
            ]
        );

        $merchant = Merchant::query()->updateOrCreate(
            ['slug' => 'provador-virtual-demo'],
            [
                'name' => 'Provador Virtual Demo Store',
                'billing_status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        $user->merchants()->sync([
            $merchant->id => ['role' => 'owner', 'is_owner' => true],
        ]);

        $company = MerchantCompany::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'external_store_id' => 'pv-demo-store',
            ],
            [
                'name' => 'Provador Virtual Loja Teste',
                'legal_name' => 'Provador Virtual Loja Teste Ltda',
                'document' => '12345678000195',
                'zip_code' => '01001000',
                'street' => 'Praca da Se',
                'number' => '100',
                'district' => 'Se',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'country' => 'BR',
                'domain' => 'provadorvirtual.online',
                'platform' => 'custom',
                'status' => 'active',
            ]
        );
        $company->ensureAccessCode();
        $this->ensureFitProfiles($merchant);

        $dressTable = $this->tableWithRows($merchant, $company, [
            'name' => 'Vestidos femininos - modelagem regular',
            'product_type' => 'dress',
            'gender' => 'female',
            'fit_profile' => 'regular',
            'notes' => 'Base demo inspirada em faixas universais de moda feminina.',
        ], [
            ['PP', 0, 80, 84, 62, 66, 88, 92, 150, 160, 45, 52],
            ['P', 1, 84, 90, 66, 72, 92, 98, 155, 168, 50, 60],
            ['M', 2, 90, 96, 72, 78, 98, 104, 160, 174, 58, 68],
            ['G', 3, 96, 104, 78, 86, 104, 112, 164, 180, 66, 78],
            ['GG', 4, 104, 112, 86, 96, 112, 120, 166, 184, 76, 90],
        ]);

        $blouseTable = $this->tableWithRows($merchant, $company, [
            'name' => 'Blusas femininas - malha canelada',
            'product_type' => 'blouse',
            'gender' => 'female',
            'fit_profile' => 'slim',
            'notes' => 'Modelagem com elasticidade moderada para blusas e tops.',
        ], [
            ['PP', 0, 78, 84, 60, 66, 84, 92, 150, 162, 42, 52],
            ['P', 1, 84, 90, 66, 72, 90, 98, 155, 168, 50, 60],
            ['M', 2, 90, 98, 72, 80, 96, 106, 160, 174, 58, 70],
            ['G', 3, 98, 106, 80, 88, 104, 114, 164, 180, 68, 82],
            ['GG', 4, 106, 116, 88, 98, 112, 124, 166, 184, 78, 94],
        ]);

        $shirtTable = $this->tableWithRows($merchant, $company, [
            'name' => 'Camisetas masculinas - regular',
            'product_type' => 'shirt',
            'gender' => 'male',
            'fit_profile' => 'regular',
            'notes' => 'Busto representa torax para pecas masculinas.',
        ], [
            ['P', 0, 88, 96, 76, 84, 88, 96, 164, 174, 58, 70],
            ['M', 1, 96, 104, 84, 92, 96, 104, 170, 180, 68, 82],
            ['G', 2, 104, 112, 92, 100, 104, 112, 176, 186, 80, 94],
            ['GG', 3, 112, 122, 100, 110, 112, 122, 180, 192, 92, 108],
            ['XGG', 4, 122, 134, 110, 122, 122, 134, 184, 198, 104, 122],
        ]);

        $pantsTable = $this->tableWithRows($merchant, $company, [
            'name' => 'Calcas masculinas - jeans reto',
            'product_type' => 'pants',
            'gender' => 'male',
            'fit_profile' => 'regular',
            'notes' => 'Faixas de cintura e quadril calibradas para jeans regular.',
        ], [
            ['38', 0, null, null, 74, 80, 90, 96, 164, 174, 56, 68],
            ['40', 1, null, null, 80, 86, 96, 102, 170, 180, 66, 78],
            ['42', 2, null, null, 86, 92, 102, 108, 174, 184, 76, 88],
            ['44', 3, null, null, 92, 100, 108, 116, 178, 190, 86, 102],
            ['46', 4, null, null, 100, 110, 116, 126, 182, 196, 98, 116],
        ]);

        $this->productWithVariants($merchant, $company, $dressTable, [
            'slug' => 'vestido-midi-aurora',
            'external_product_id' => 'pv-demo-vestido-midi-aurora',
            'sku' => 'PV-AURORA-MIDI',
            'name' => 'Vestido Midi Aurora',
            'description' => 'Vestido midi em viscose leve, com cintura marcada e caimento regular.',
            'category' => 'Vestidos',
            'gender' => 'female',
            'fit_profile' => 'regular',
            'image_url' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=900&q=80',
            'color' => 'Verde oliva',
            'price' => 189.90,
        ], ['PP', 'P', 'M', 'G', 'GG']);

        $this->productWithVariants($merchant, $company, $blouseTable, [
            'slug' => 'blusa-canelada-solar',
            'external_product_id' => 'pv-demo-blusa-canelada-solar',
            'sku' => 'PV-SOLAR-BLUSA',
            'name' => 'Blusa Canelada Solar',
            'description' => 'Blusa feminina em malha canelada com elasticidade moderada e gola redonda.',
            'category' => 'Blusas',
            'gender' => 'female',
            'fit_profile' => 'slim',
            'image_url' => 'https://images.unsplash.com/photo-1554568218-0f1715e72254?auto=format&fit=crop&w=900&q=80',
            'color' => 'Off white',
            'price' => 99.90,
        ], ['PP', 'P', 'M', 'G', 'GG']);

        $this->productWithVariants($merchant, $company, $shirtTable, [
            'slug' => 'camiseta-essencial-marinho',
            'external_product_id' => 'pv-demo-camiseta-essencial-marinho',
            'sku' => 'PV-ESSENCIAL-CAMISETA',
            'name' => 'Camiseta Essencial Marinho',
            'description' => 'Camiseta masculina em algodao penteado, modelagem regular e toque macio.',
            'category' => 'Camisetas',
            'gender' => 'male',
            'fit_profile' => 'regular',
            'image_url' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80',
            'color' => 'Azul marinho',
            'price' => 79.90,
        ], ['P', 'M', 'G', 'GG', 'XGG']);

        $this->productWithVariants($merchant, $company, $pantsTable, [
            'slug' => 'calca-jeans-reta-masculina',
            'external_product_id' => 'pv-demo-calca-jeans-reta-masculina',
            'sku' => 'PV-JEANS-RETA',
            'name' => 'Calca Jeans Reta Masculina',
            'description' => 'Calca jeans masculina com cintura media, perna reta e tecido com leve elastano.',
            'category' => 'Calcas',
            'gender' => 'male',
            'fit_profile' => 'regular',
            'image_url' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=900&q=80',
            'color' => 'Jeans escuro',
            'price' => 219.90,
        ], ['38', '40', '42', '44', '46']);
        $this->seedMerchantOrders($merchant, $company);
        $this->seedMerchantReturns($merchant, $company);

        WidgetInstall::query()->updateOrCreate(
            ['public_key' => 'pv_demo_luna'],
            [
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company->id,
                'platform' => 'custom',
                'allowed_domains' => ['localhost', '127.0.0.1', 'provadorvirtual.online'],
                'theme' => [
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
                    'modal' => WidgetModalCatalog::default(),
                    'placement' => WidgetPlacementCatalog::default(),
                ],
                'is_active' => true,
            ]
        );

        TransactionalEmail::ensureDefaults();
    }

    private function tableWithRows(Merchant $merchant, MerchantCompany $company, array $tableData, array $rows): MeasurementTable
    {
        $table = MeasurementTable::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company->id,
                'name' => $tableData['name'],
            ],
            [
                'product_type' => $tableData['product_type'],
                'gender' => $tableData['gender'],
                'fit_profile' => $tableData['fit_profile'],
                'unit' => 'cm',
                'status' => 'active',
                'source' => 'demo',
                'notes' => $tableData['notes'],
            ]
        );

        foreach ($rows as [$size, $sort, $bustMin, $bustMax, $waistMin, $waistMax, $hipMin, $hipMax, $heightMin, $heightMax, $weightMin, $weightMax]) {
            MeasurementTableRow::query()->updateOrCreate(
                ['measurement_table_id' => $table->id, 'size_label' => $size],
                [
                    'sort_order' => $sort,
                    'bust_min' => $bustMin,
                    'bust_max' => $bustMax,
                    'waist_min' => $waistMin,
                    'waist_max' => $waistMax,
                    'hip_min' => $hipMin,
                    'hip_max' => $hipMax,
                    'height_min' => $heightMin,
                    'height_max' => $heightMax,
                    'weight_min' => $weightMin,
                    'weight_max' => $weightMax,
                    'metadata' => ['source' => 'seed_demo_universal'],
                ]
            );
        }

        return $table;
    }

    private function ensureFitProfiles(Merchant $merchant): void
    {
        $profiles = [
            ['name' => 'Slim', 'code' => 'slim', 'fit_intensity' => 'slim', 'stretch_level' => 'medium', 'description' => 'Mais ajustada ao corpo, indicada para peças com menor folga.'],
            ['name' => 'Regular', 'code' => 'regular', 'fit_intensity' => 'regular', 'stretch_level' => 'medium', 'description' => 'Caimento padrão, base segura para a maioria das tabelas.'],
            ['name' => 'Ampla', 'code' => 'oversized', 'fit_intensity' => 'oversized', 'stretch_level' => 'low', 'description' => 'Mais solta e com volume proposital na peça.'],
            ['name' => 'Solta', 'code' => 'loose', 'fit_intensity' => 'relaxed', 'stretch_level' => 'medium', 'description' => 'Caimento relaxado, com folga moderada.'],
            ['name' => 'Conforto', 'code' => 'comfort', 'fit_intensity' => 'relaxed', 'stretch_level' => 'high', 'description' => 'Modelagem confortável, útil para peças com elasticidade ou uso prolongado.'],
        ];

        foreach ($profiles as $profile) {
            FitProfile::query()->updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => null,
                    'code' => $profile['code'],
                ],
                [
                    'name' => $profile['name'],
                    'description' => $profile['description'],
                    'product_type' => null,
                    'gender' => null,
                    'fit_intensity' => $profile['fit_intensity'],
                    'stretch_level' => $profile['stretch_level'],
                    'status' => 'active',
                    'metadata' => ['source' => 'seed_default'],
                ]
            );
        }
    }

    private function productWithVariants(Merchant $merchant, MerchantCompany $company, MeasurementTable $table, array $productData, array $sizes): Product
    {
        $product = Product::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'slug' => $productData['slug'],
            ],
            [
                'merchant_company_id' => $company->id,
                'measurement_table_id' => $table->id,
                'external_product_id' => $productData['external_product_id'],
                'sku' => $productData['sku'],
                'name' => $productData['name'],
                'description' => $productData['description'],
                'category' => $productData['category'],
                'gender' => $productData['gender'],
                'fit_profile' => $productData['fit_profile'],
                'status' => 'active',
                'image_url' => $productData['image_url'],
                'metadata' => ['demo' => true, 'storefront' => true],
            ]
        );

        foreach ($sizes as $index => $size) {
            ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'size_label' => $size],
                [
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company->id,
                    'external_variant_id' => $productData['sku'].'-'.$size,
                    'sku' => $productData['sku'].'-'.$size,
                    'color' => $productData['color'],
                    'price' => $productData['price'],
                    'stock_quantity' => max(3, 16 - ($index * 2)),
                    'is_active' => true,
                    'metadata' => ['demo' => true],
                ]
            );
        }

        return $product;
    }

    private function seedMerchantOrders(Merchant $merchant, MerchantCompany $company): void
    {
        $orders = [
            [
                'reference' => 'PV-ORDER-2026-001',
                'ordered_at' => now()->subDays(2),
                'status' => 'paid',
                'source' => 'csv',
                'source_platform' => 'custom',
                'items' => [
                    ['sku' => 'PV-AURORA-MIDI-M', 'product_name' => 'Vestido Midi Aurora', 'ordered_size' => 'M', 'quantity' => 1, 'unit_price_cents' => 18990, 'used_virtual_try_on' => true, 'recommended_size' => 'M', 'recommendation_confidence' => 96],
                    ['sku' => 'PV-SOLAR-BLUSA-P', 'product_name' => 'Blusa Canelada Solar', 'ordered_size' => 'P', 'quantity' => 1, 'unit_price_cents' => 9990, 'used_virtual_try_on' => false, 'recommended_size' => null, 'recommendation_confidence' => null],
                ],
            ],
            [
                'reference' => 'PV-ORDER-2026-002',
                'ordered_at' => now()->subDay(),
                'status' => 'paid',
                'source' => 'csv',
                'source_platform' => 'custom',
                'items' => [
                    ['sku' => 'PV-ESSENCIAL-CAMISETA-M', 'product_name' => 'Camiseta Essencial Marinho', 'ordered_size' => 'M', 'quantity' => 2, 'unit_price_cents' => 7990, 'used_virtual_try_on' => true, 'recommended_size' => 'M', 'recommendation_confidence' => 88],
                ],
            ],
            [
                'reference' => 'PV-ORDER-2026-003',
                'ordered_at' => now()->subHours(8),
                'status' => 'pending',
                'source' => 'manual',
                'source_platform' => 'custom',
                'items' => [
                    ['sku' => 'PV-JEANS-RETA-42', 'product_name' => 'Calca Jeans Reta Masculina', 'ordered_size' => '42', 'quantity' => 1, 'unit_price_cents' => 21990, 'used_virtual_try_on' => false, 'recommended_size' => null, 'recommendation_confidence' => null],
                ],
            ],
        ];

        foreach ($orders as $seededOrder) {
            $order = MerchantOrder::query()->updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company->id,
                    'order_reference_hash' => hash('sha256', $seededOrder['reference']),
                ],
                [
                    'source' => $seededOrder['source'],
                    'source_platform' => $seededOrder['source_platform'],
                    'order_reference' => $seededOrder['reference'],
                    'status' => $seededOrder['status'],
                    'ordered_at' => $seededOrder['ordered_at'],
                    'currency' => 'BRL',
                ]
            );

            $order->items()->delete();

            $items = collect($seededOrder['items'])->map(function (array $item): array {
                $lineTotal = $item['unit_price_cents'] * $item['quantity'];

                return [
                    'sku' => $item['sku'],
                    'product_name' => $item['product_name'],
                    'ordered_size' => $item['ordered_size'],
                    'recommended_size' => $item['recommended_size'],
                    'recommendation_confidence' => $item['recommendation_confidence'],
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $item['unit_price_cents'],
                    'line_total_cents' => $lineTotal,
                    'used_virtual_try_on' => $item['used_virtual_try_on'],
                    'metadata' => ['source' => 'seed_demo_orders'],
                ];
            });

            $order->items()->createMany($items->all());

            $order->update([
                'items_count' => $items->count(),
                'total_quantity' => (int) $items->sum('quantity'),
                'total_amount_cents' => (int) $items->sum('line_total_cents'),
                'used_virtual_try_on' => $items->contains('used_virtual_try_on', true),
                'assisted_items_count' => $items->where('used_virtual_try_on', true)->count(),
                'assisted_revenue_cents' => (int) $items->where('used_virtual_try_on', true)->sum('line_total_cents'),
                'metadata' => ['source' => 'seed_demo_orders'],
            ]);
        }
    }

    private function seedMerchantReturns(Merchant $merchant, MerchantCompany $company): void
    {
        $seededReturns = [
            [
                'reference' => 'PV-RETURN-2026-001',
                'order_reference' => 'PV-ORDER-2026-001',
                'processed_at' => now()->subDay(),
                'status' => 'returned',
                'item' => [
                    'sku' => 'PV-AURORA-MIDI-M',
                    'product_name' => 'Vestido Midi Aurora',
                    'ordered_size' => 'M',
                    'ideal_size' => 'G',
                    'returned_size' => 'M',
                    'exchanged_to_size' => null,
                    'return_reason' => 'size_too_small',
                    'quantity' => 1,
                    'refund_amount_cents' => 18990,
                    'used_virtual_try_on' => true,
                    'recommendation_confidence' => 96,
                ],
            ],
            [
                'reference' => 'PV-RETURN-2026-002',
                'order_reference' => 'PV-ORDER-2026-002',
                'processed_at' => now()->subHours(10),
                'status' => 'exchange',
                'item' => [
                    'sku' => 'PV-ESSENCIAL-CAMISETA-M',
                    'product_name' => 'Camiseta Essencial Marinho',
                    'ordered_size' => 'M',
                    'ideal_size' => 'P',
                    'returned_size' => 'M',
                    'exchanged_to_size' => 'P',
                    'return_reason' => 'size_too_large',
                    'quantity' => 1,
                    'refund_amount_cents' => 7990,
                    'used_virtual_try_on' => true,
                    'recommendation_confidence' => 88,
                ],
            ],
        ];

        foreach ($seededReturns as $seededReturn) {
            $return = MerchantReturn::query()->updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company->id,
                    'return_reference_hash' => hash('sha256', $seededReturn['reference']),
                ],
                [
                    'source' => 'import',
                    'source_platform' => 'custom',
                    'return_reference' => $seededReturn['reference'],
                    'order_reference' => $seededReturn['order_reference'],
                    'order_reference_hash' => hash('sha256', $seededReturn['order_reference']),
                    'status' => $seededReturn['status'],
                    'processed_at' => $seededReturn['processed_at'],
                    'metadata' => ['source' => 'seed_demo_returns'],
                ]
            );

            $return->items()->delete();
            $return->items()->create([
                ...$seededReturn['item'],
                'ordered_at' => now()->subDays(2),
                'status' => $seededReturn['status'],
                'metadata' => ['source' => 'seed_demo_returns'],
            ]);

            $return->update([
                'items_count' => 1,
                'total_quantity' => (int) $seededReturn['item']['quantity'],
                'refund_amount_cents' => (int) $seededReturn['item']['refund_amount_cents'],
                'used_virtual_try_on' => (bool) $seededReturn['item']['used_virtual_try_on'],
                'assisted_items_count' => $seededReturn['item']['used_virtual_try_on'] ? 1 : 0,
                'assisted_refund_cents' => $seededReturn['item']['used_virtual_try_on']
                    ? (int) $seededReturn['item']['refund_amount_cents']
                    : 0,
            ]);
        }
    }
}
