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
        Schema::create('measurement_table_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_table_id')->constrained()->cascadeOnDelete();
            $table->string('size_label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('bust_min', 5, 2)->nullable();
            $table->decimal('bust_max', 5, 2)->nullable();
            $table->decimal('waist_min', 5, 2)->nullable();
            $table->decimal('waist_max', 5, 2)->nullable();
            $table->decimal('hip_min', 5, 2)->nullable();
            $table->decimal('hip_max', 5, 2)->nullable();
            $table->decimal('height_min', 5, 2)->nullable();
            $table->decimal('height_max', 5, 2)->nullable();
            $table->decimal('weight_min', 5, 2)->nullable();
            $table->decimal('weight_max', 5, 2)->nullable();
            $table->decimal('length_min', 5, 2)->nullable();
            $table->decimal('length_max', 5, 2)->nullable();
            $table->decimal('shoulder_min', 5, 2)->nullable();
            $table->decimal('shoulder_max', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['measurement_table_id', 'size_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurement_table_rows');
    }
};
