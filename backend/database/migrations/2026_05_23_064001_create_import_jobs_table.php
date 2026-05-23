<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('source_format');
            $table->string('filename')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('summary')->nullable();
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'type']);
            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
