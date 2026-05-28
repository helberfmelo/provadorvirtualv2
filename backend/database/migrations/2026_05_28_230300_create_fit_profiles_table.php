<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fit_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 80);
            $table->text('description')->nullable();
            $table->string('product_type')->nullable();
            $table->string('gender')->nullable();
            $table->string('fit_intensity')->default('regular');
            $table->string('stretch_level')->default('medium');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'merchant_company_id']);
            $table->index(['merchant_id', 'code']);
            $table->index(['status', 'fit_intensity']);
        });

        if (! Schema::hasTable('merchants')) {
            return;
        }

        $now = now();
        $profiles = [
            ['name' => 'Slim', 'code' => 'slim', 'fit_intensity' => 'slim', 'stretch_level' => 'medium', 'description' => 'Mais ajustada ao corpo, indicada para peças com menor folga.'],
            ['name' => 'Regular', 'code' => 'regular', 'fit_intensity' => 'regular', 'stretch_level' => 'medium', 'description' => 'Caimento padrão, base segura para a maioria das tabelas.'],
            ['name' => 'Ampla', 'code' => 'oversized', 'fit_intensity' => 'oversized', 'stretch_level' => 'low', 'description' => 'Mais solta e com volume proposital na peça.'],
            ['name' => 'Solta', 'code' => 'loose', 'fit_intensity' => 'relaxed', 'stretch_level' => 'medium', 'description' => 'Caimento relaxado, com folga moderada.'],
            ['name' => 'Conforto', 'code' => 'comfort', 'fit_intensity' => 'relaxed', 'stretch_level' => 'high', 'description' => 'Modelagem confortável, útil para peças com elasticidade ou uso prolongado.'],
        ];

        foreach (DB::table('merchants')->select('id')->cursor() as $merchant) {
            foreach ($profiles as $profile) {
                DB::table('fit_profiles')->insert([
                    'merchant_id' => $merchant->id,
                    'merchant_company_id' => null,
                    'name' => $profile['name'],
                    'code' => $profile['code'],
                    'description' => $profile['description'],
                    'product_type' => null,
                    'gender' => null,
                    'fit_intensity' => $profile['fit_intensity'],
                    'stretch_level' => $profile['stretch_level'],
                    'status' => 'active',
                    'metadata' => json_encode(['source' => 'sprint_113_default']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_profiles');
    }
};
