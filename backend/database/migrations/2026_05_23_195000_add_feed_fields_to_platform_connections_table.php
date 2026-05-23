<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('platform_connections', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_connections', 'feed_url')) {
                $table->string('feed_url')->nullable()->after('api_base_url');
            }

            if (! Schema::hasColumn('platform_connections', 'feed_format')) {
                $table->string('feed_format')->default('google_xml')->after('feed_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_connections', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_connections', 'feed_format')) {
                $table->dropColumn('feed_format');
            }

            if (Schema::hasColumn('platform_connections', 'feed_url')) {
                $table->dropColumn('feed_url');
            }
        });
    }
};
