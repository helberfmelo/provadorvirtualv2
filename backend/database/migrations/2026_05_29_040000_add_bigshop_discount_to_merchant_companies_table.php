<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('merchant_companies', 'bigshop_discount_active')) {
                $table->boolean('bigshop_discount_active')->default(false)->after('platform');
            }
        });

        DB::table('merchant_companies')
            ->where('platform', 'bigshop')
            ->update(['bigshop_discount_active' => true]);
    }

    public function down(): void
    {
        Schema::table('merchant_companies', function (Blueprint $table): void {
            if (Schema::hasColumn('merchant_companies', 'bigshop_discount_active')) {
                $table->dropColumn('bigshop_discount_active');
            }
        });
    }
};
