<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained('merchant_companies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('context', 60);
            $table->string('document_type', 60);
            $table->string('terms_version', 40);
            $table->string('privacy_version', 40)->nullable();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_company_id', 'accepted_at']);
            $table->index(['context', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
