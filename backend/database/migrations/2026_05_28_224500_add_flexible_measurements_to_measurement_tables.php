<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurement_tables', function (Blueprint $table): void {
            if (! Schema::hasColumn('measurement_tables', 'measurement_target')) {
                $table->string('measurement_target')->default('body')->after('fit_profile');
            }

            if (! Schema::hasColumn('measurement_tables', 'size_system')) {
                $table->string('size_system')->default('br_alpha')->after('measurement_target');
            }

            if (! Schema::hasColumn('measurement_tables', 'range_mode')) {
                $table->string('range_mode')->default('min_max')->after('size_system');
            }
        });

        Schema::table('measurement_table_rows', function (Blueprint $table): void {
            if (! Schema::hasColumn('measurement_table_rows', 'measurements')) {
                $table->json('measurements')->nullable()->after('shoulder_max');
            }

            if (! Schema::hasColumn('measurement_table_rows', 'composite_measurements')) {
                $table->json('composite_measurements')->nullable()->after('measurements');
            }
        });
    }

    public function down(): void
    {
        Schema::table('measurement_table_rows', function (Blueprint $table): void {
            if (Schema::hasColumn('measurement_table_rows', 'composite_measurements')) {
                $table->dropColumn('composite_measurements');
            }

            if (Schema::hasColumn('measurement_table_rows', 'measurements')) {
                $table->dropColumn('measurements');
            }
        });

        Schema::table('measurement_tables', function (Blueprint $table): void {
            foreach (['range_mode', 'size_system', 'measurement_target'] as $column) {
                if (Schema::hasColumn('measurement_tables', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
