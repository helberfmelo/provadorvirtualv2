<?php

namespace Database\Seeders;

use App\Models\MeasurementTable;
use App\Models\MeasurementTableRow;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WidgetInstall;
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
            ['slug' => 'loja-luna-demo'],
            [
                'name' => 'Loja Luna Demo',
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
                'external_store_id' => 'demo-store',
            ],
            [
                'name' => 'Luna Moda Online',
                'legal_name' => 'Luna Moda Online Demo',
                'domain' => 'provadorvirtual.online',
                'platform' => 'custom',
                'status' => 'active',
            ]
        );

        $table = MeasurementTable::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company->id,
                'name' => 'Vestidos femininos - modelagem regular',
            ],
            [
                'product_type' => 'dress',
                'gender' => 'female',
                'fit_profile' => 'regular',
                'unit' => 'cm',
                'status' => 'active',
                'source' => 'demo',
                'notes' => 'Tabela ficticia para validar o fluxo do produto teste.',
            ]
        );

        $rows = [
            ['PP', 0, 80, 84, 62, 66, 88, 92, 150, 160, 45, 52],
            ['P', 1, 84, 90, 66, 72, 92, 98, 155, 168, 50, 60],
            ['M', 2, 90, 96, 72, 78, 98, 104, 160, 174, 58, 68],
            ['G', 3, 96, 104, 78, 86, 104, 112, 164, 180, 66, 78],
            ['GG', 4, 104, 112, 86, 96, 112, 120, 166, 184, 76, 90],
        ];

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
                ]
            );
        }

        $product = Product::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'slug' => 'vestido-luna-midi',
            ],
            [
                'merchant_company_id' => $company->id,
                'measurement_table_id' => $table->id,
                'external_product_id' => 'demo-product-vestido-luna',
                'sku' => 'LUNA-MIDI',
                'name' => 'Vestido Luna Midi',
                'description' => 'Vestido midi em viscose leve, com caimento regular e cintura marcada.',
                'category' => 'Vestidos',
                'gender' => 'female',
                'fit_profile' => 'regular',
                'status' => 'active',
                'image_url' => '/images/demo-product.jpg',
                'metadata' => ['demo' => true],
            ]
        );

        foreach (['PP', 'P', 'M', 'G', 'GG'] as $index => $size) {
            ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'size_label' => $size],
                [
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => $company->id,
                    'external_variant_id' => 'demo-variant-'.$size,
                    'sku' => 'LUNA-MIDI-'.$size,
                    'color' => 'Verde oliva',
                    'price' => 189.90,
                    'stock_quantity' => 12 - $index,
                    'is_active' => true,
                    'metadata' => ['demo' => true],
                ]
            );
        }

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
                ],
                'is_active' => true,
            ]
        );
    }
}
