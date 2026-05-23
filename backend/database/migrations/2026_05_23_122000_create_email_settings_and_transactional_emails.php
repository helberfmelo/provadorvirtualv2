<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40)->default('saas')->unique();
            $table->string('mailer', 40)->default('smtp');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('encryption', 20)->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('transactional_emails', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactional_emails');
        Schema::dropIfExists('email_settings');
    }
};
