<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('taxonomy_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category_type')->default('other');
            $table->string('gender')->nullable();
            $table->string('age_group')->nullable();
            $table->json('translations')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'status']);
            $table->index(['category_type', 'gender', 'age_group']);
        });

        Schema::create('merchant_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxonomy_category_id')->nullable()->constrained('taxonomy_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('source')->default('manual');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'merchant_company_id']);
            $table->index(['merchant_id', 'slug']);
            $table->index(['taxonomy_category_id', 'status']);
        });

        $this->seedTaxonomy();
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_categories');
        Schema::dropIfExists('taxonomy_categories');
    }

    private function seedTaxonomy(): void
    {
        $now = now();
        $roots = [
            ['name' => 'Superior', 'type' => 'top', 'children' => ['Camisas', 'Camisetas', 'Blusas', 'Casacos']],
            ['name' => 'Inferior', 'type' => 'bottom', 'children' => ['Calças', 'Bermudas', 'Shorts', 'Saias']],
            ['name' => 'Corpo inteiro', 'type' => 'full_body', 'children' => ['Vestidos', 'Macacões', 'Conjuntos']],
            ['name' => 'Calçados', 'type' => 'shoe', 'children' => ['Tênis', 'Sapatos', 'Sandálias', 'Botas']],
            ['name' => 'Íntimo superior', 'type' => 'top_underwear', 'children' => ['Sutiãs', 'Tops íntimos']],
            ['name' => 'Íntimo inferior', 'type' => 'bottom_underwear', 'children' => ['Calcinhas', 'Cuecas']],
            ['name' => 'Acessórios', 'type' => 'accessory', 'children' => ['Cintos', 'Chapéus', 'Bolsas']],
        ];

        foreach ($roots as $root) {
            $rootId = DB::table('taxonomy_categories')->insertGetId([
                'parent_id' => null,
                'name' => $root['name'],
                'slug' => Str::slug($root['name']),
                'category_type' => $root['type'],
                'gender' => null,
                'age_group' => null,
                'translations' => json_encode(['pt_BR' => $root['name']]),
                'status' => 'active',
                'metadata' => json_encode(['source' => 'sprint_137_seed', 'level' => 'root']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($root['children'] as $child) {
                DB::table('taxonomy_categories')->insert([
                    'parent_id' => $rootId,
                    'name' => $child,
                    'slug' => Str::slug($child),
                    'category_type' => $root['type'],
                    'gender' => null,
                    'age_group' => null,
                    'translations' => json_encode(['pt_BR' => $child]),
                    'status' => 'active',
                    'metadata' => json_encode(['source' => 'sprint_137_seed', 'level' => 'subcategory']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
