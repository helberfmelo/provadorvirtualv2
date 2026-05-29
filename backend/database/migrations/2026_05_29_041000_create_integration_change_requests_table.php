<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_platform', 80);
            $table->string('to_platform', 80);
            $table->string('status', 40)->default('pending')->index();
            $table->string('terms_version', 40);
            $table->dateTime('terms_accepted_at')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('resolved_at')->nullable();
            $table->string('payment_link')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_company_id', 'status'], 'integration_change_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_change_requests');
    }
};
