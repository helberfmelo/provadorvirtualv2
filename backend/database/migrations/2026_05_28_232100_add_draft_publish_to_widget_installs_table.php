<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widget_installs', function (Blueprint $table): void {
            if (! Schema::hasColumn('widget_installs', 'draft_platform')) {
                $table->string('draft_platform')->nullable()->after('platform');
            }

            if (! Schema::hasColumn('widget_installs', 'draft_allowed_domains')) {
                $table->json('draft_allowed_domains')->nullable()->after('allowed_domains');
            }

            if (! Schema::hasColumn('widget_installs', 'draft_theme')) {
                $table->json('draft_theme')->nullable()->after('theme');
            }

            if (! Schema::hasColumn('widget_installs', 'draft_is_active')) {
                $table->boolean('draft_is_active')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('widget_installs', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('draft_is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('widget_installs', function (Blueprint $table): void {
            foreach (['published_at', 'draft_is_active', 'draft_theme', 'draft_allowed_domains', 'draft_platform'] as $column) {
                if (Schema::hasColumn('widget_installs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
