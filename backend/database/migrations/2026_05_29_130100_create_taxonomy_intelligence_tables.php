<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version')->unique();
            $table->string('label');
            $table->string('status')->default('active');
            $table->json('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('taxonomy_mapping_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxonomy_version_id')->nullable()->constrained('taxonomy_versions')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_category_id')->nullable()->constrained('merchant_categories')->nullOnDelete();
            $table->foreignId('merchant_brand_id')->nullable()->constrained('merchant_brands')->nullOnDelete();
            $table->string('suggestion_type');
            $table->string('source')->default('learning');
            $table->string('original_value');
            $table->string('suggested_target_type');
            $table->foreignId('taxonomy_category_id')->nullable()->constrained('taxonomy_categories')->nullOnDelete();
            $table->foreignId('normalized_brand_id')->nullable()->constrained('normalized_brands')->nullOnDelete();
            $table->string('suggested_name')->nullable();
            $table->decimal('confidence_score', 5, 4)->default(0);
            $table->string('confidence_level')->default('low');
            $table->string('status')->default('pending');
            $table->json('reasons')->nullable();
            $table->json('impact')->nullable();
            $table->json('context')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'merchant_company_id', 'status']);
            $table->index(['suggestion_type', 'confidence_level']);
            $table->index(['merchant_category_id', 'status']);
            $table->index(['merchant_brand_id', 'status']);
        });

        Schema::create('taxonomy_learning_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxonomy_mapping_suggestion_id')->nullable()->constrained('taxonomy_mapping_suggestions')->nullOnDelete();
            $table->string('event_type');
            $table->string('source')->default('taxonomy_intelligence');
            $table->string('target_type');
            $table->string('original_value')->nullable();
            $table->string('normalized_value')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->json('context')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'merchant_company_id', 'event_type']);
            $table->index(['target_type', 'created_at']);
        });

        DB::table('taxonomy_versions')->insert([
            'version' => '2026.05.29-sprint138',
            'label' => 'Taxonomia inteligente inicial',
            'status' => 'active',
            'summary' => json_encode([
                'source' => 'sprint_138',
                'feeds' => ['categorias', 'marcas', 'genero', 'faixa_etaria', 'modelagem', 'sistema_tamanho'],
            ]),
            'published_at' => now(),
            'metadata' => json_encode([
                'benchmark' => 'sizebay_readonly',
                'sensitive_data' => false,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_learning_events');
        Schema::dropIfExists('taxonomy_mapping_suggestions');
        Schema::dropIfExists('taxonomy_versions');
    }
};
