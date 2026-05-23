<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactional_email_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transactional_email_id')->nullable()->constrained('transactional_emails')->nullOnDelete();
            $table->foreignId('checkout_session_id')->nullable()->constrained('checkout_sessions')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 80)->index();
            $table->string('recipient_email')->nullable()->index();
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->json('context')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['checkout_session_id', 'code', 'status']);
            $table->index(['merchant_company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactional_email_sends');
    }
};
