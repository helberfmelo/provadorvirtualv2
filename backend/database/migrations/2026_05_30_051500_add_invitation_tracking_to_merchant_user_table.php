<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_user', function (Blueprint $table): void {
            $table->string('invitation_status')->default('accepted')->after('status');
            $table->timestamp('invited_at')->nullable()->after('invitation_status');
            $table->timestamp('accepted_at')->nullable()->after('invited_at');
            $table->index(['merchant_id', 'invitation_status']);
        });
    }

    public function down(): void
    {
        Schema::table('merchant_user', function (Blueprint $table): void {
            $table->dropIndex(['merchant_id', 'invitation_status']);
            $table->dropColumn(['invitation_status', 'invited_at', 'accepted_at']);
        });
    }
};
