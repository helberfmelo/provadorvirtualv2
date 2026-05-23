<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('merchant_company_id')->nullable()->after('merchant_id')->constrained()->nullOnDelete();
            $table->string('module')->nullable()->after('category')->index();
            $table->string('action')->nullable()->after('module')->index();
            $table->index(['merchant_id', 'merchant_company_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['merchant_company_id']);
            $table->dropIndex(['merchant_id', 'merchant_company_id']);
            $table->dropColumn(['merchant_company_id', 'module', 'action']);
        });
    }
};
