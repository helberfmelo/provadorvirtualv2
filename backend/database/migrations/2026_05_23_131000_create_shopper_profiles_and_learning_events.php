<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopper_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('profile_type')->default('anonymous');
            $table->string('status')->default('active');
            $table->string('write_token_hash')->nullable();
            $table->string('consent_version')->nullable();
            $table->timestamp('consent_given_at')->nullable();
            $table->json('measurements')->nullable();
            $table->json('preferences')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->decimal('outlier_score', 5, 2)->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'merchant_company_id', 'status']);
            $table->index(['profile_type', 'last_seen_at']);
        });

        Schema::table('recommendation_sessions', function (Blueprint $table) {
            $table->foreignId('shopper_profile_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
            $table->string('shopper_profile_uuid')->nullable()->after('shopper_profile_id');
            $table->boolean('consent_given')->default(false)->after('shopper_profile_uuid');
            $table->json('profile_snapshot')->nullable()->after('shopper_profile');

            $table->index(['shopper_profile_id', 'created_at']);
        });

        Schema::table('recommendation_logs', function (Blueprint $table) {
            $table->decimal('outlier_score', 5, 2)->default(0)->after('warnings');
            $table->string('learning_status')->default('candidate')->after('outlier_score');
            $table->string('learning_reason')->nullable()->after('learning_status');

            $table->index(['merchant_id', 'learning_status', 'created_at']);
        });

        Schema::create('recommendation_learning_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shopper_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recommendation_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recommendation_feedback_id')->nullable()->constrained('recommendation_feedbacks')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('signal')->nullable();
            $table->string('recommended_size')->nullable();
            $table->string('selected_size')->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->decimal('outlier_score', 5, 2)->default(0);
            $table->decimal('learning_weight', 6, 3)->default(0);
            $table->string('status')->default('candidate');
            $table->string('reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['merchant_id', 'event_type', 'status']);
            $table->index(['merchant_id', 'product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_learning_events');

        Schema::table('recommendation_logs', function (Blueprint $table) {
            $table->dropIndex(['merchant_id', 'learning_status', 'created_at']);
            $table->dropColumn(['outlier_score', 'learning_status', 'learning_reason']);
        });

        Schema::table('recommendation_sessions', function (Blueprint $table) {
            $table->dropIndex(['shopper_profile_id', 'created_at']);
            $table->dropConstrainedForeignId('shopper_profile_id');
            $table->dropColumn(['shopper_profile_uuid', 'consent_given', 'profile_snapshot']);
        });

        Schema::dropIfExists('shopper_profiles');
    }
};
