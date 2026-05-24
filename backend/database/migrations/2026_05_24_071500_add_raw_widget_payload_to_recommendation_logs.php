<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_logs', function (Blueprint $table): void {
            $table->json('raw_widget_payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_logs', function (Blueprint $table): void {
            $table->dropColumn('raw_widget_payload');
        });
    }
};
