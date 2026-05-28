<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_connections', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_connections', 'import_rules')) {
                $table->json('import_rules')->nullable()->after('feed_format');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_connections', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_connections', 'import_rules')) {
                $table->dropColumn('import_rules');
            }
        });
    }
};
