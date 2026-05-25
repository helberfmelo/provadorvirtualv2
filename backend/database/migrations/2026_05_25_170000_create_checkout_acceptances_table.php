<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lead_email');
            $table->string('company_document', 20)->nullable();
            $table->string('terms_version', 40);
            $table->string('privacy_version', 40);
            $table->boolean('accepted_terms')->default(false);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_acceptances');
    }
};
