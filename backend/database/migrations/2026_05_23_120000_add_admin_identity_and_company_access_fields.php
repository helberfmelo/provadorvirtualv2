<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('cpf', 11)->nullable()->unique()->after('email');
        });

        Schema::table('merchant_companies', function (Blueprint $table): void {
            $table->string('access_code', 12)->nullable()->unique()->after('id');
            $table->string('zip_code', 12)->nullable()->after('document');
            $table->string('street')->nullable()->after('zip_code');
            $table->string('number', 40)->nullable()->after('street');
            $table->string('complement')->nullable()->after('number');
            $table->string('district')->nullable()->after('complement');
            $table->string('city')->nullable()->after('district');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('country', 2)->default('BR')->after('state');

            $table->index(['access_code']);
            $table->index(['document']);
        });

        $year = now()->year;

        DB::table('merchant_companies')
            ->orderBy('id')
            ->select(['id', 'access_code'])
            ->chunkById(100, function ($companies) use ($year): void {
                foreach ($companies as $company) {
                    if ($company->access_code) {
                        continue;
                    }

                    DB::table('merchant_companies')
                        ->where('id', $company->id)
                        ->update([
                            'access_code' => $year.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('merchant_companies', function (Blueprint $table): void {
            $table->dropIndex(['access_code']);
            $table->dropIndex(['document']);
            $table->dropUnique(['access_code']);
            $table->dropColumn([
                'access_code',
                'zip_code',
                'street',
                'number',
                'complement',
                'district',
                'city',
                'state',
                'country',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['cpf']);
            $table->dropColumn('cpf');
        });
    }
};
