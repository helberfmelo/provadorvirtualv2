<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('role');
            $table->json('permissions')->nullable()->after('status');
            $table->index(['status', 'role']);
        });

        Schema::table('merchant_user', function (Blueprint $table): void {
            $table->foreignId('merchant_company_id')
                ->nullable()
                ->after('user_id')
                ->constrained('merchant_companies')
                ->nullOnDelete();
            $table->string('status')->default('active')->after('role');
            $table->json('permissions')->nullable()->after('is_owner');
            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('merchant_user', function (Blueprint $table): void {
            $table->dropIndex(['merchant_id', 'status']);
            $table->dropConstrainedForeignId('merchant_company_id');
            $table->dropColumn(['status', 'permissions']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status', 'role']);
            $table->dropColumn(['status', 'permissions']);
        });
    }
};
