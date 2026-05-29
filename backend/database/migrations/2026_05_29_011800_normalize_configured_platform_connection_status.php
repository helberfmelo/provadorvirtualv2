<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_connections')
            ->where('status', 'draft')
            ->where(function ($query): void {
                $query
                    ->where(function ($bigshop): void {
                        $bigshop
                            ->where('platform', 'bigshop')
                            ->whereNotNull('external_store_id')
                            ->where('external_store_id', '<>', '')
                            ->where(function ($credentials): void {
                                $credentials
                                    ->where(function ($token): void {
                                        $token
                                            ->whereNotNull('access_token_encrypted')
                                            ->where('access_token_encrypted', '<>', '');
                                    })
                                    ->orWhere(function ($feed): void {
                                        $feed
                                            ->whereNotNull('feed_url')
                                            ->where('feed_url', '<>', '');
                                    });
                            });
                    })
                    ->orWhere(function ($manual): void {
                        $manual
                            ->where('platform', '<>', 'bigshop')
                            ->where(function ($configured): void {
                                $configured
                                    ->where(function ($feed): void {
                                        $feed
                                            ->whereNotNull('feed_url')
                                            ->where('feed_url', '<>', '');
                                    })
                                    ->orWhere(function ($api): void {
                                        $api
                                            ->whereNotNull('api_base_url')
                                            ->where('api_base_url', '<>', '')
                                            ->whereNotNull('access_token_encrypted')
                                            ->where('access_token_encrypted', '<>', '');
                                    })
                                    ->orWhere(function ($store): void {
                                        $store
                                            ->whereNotNull('external_store_id')
                                            ->where('external_store_id', '<>', '');
                                    });
                            });
                    });
            })
            ->update([
                'status' => 'configured',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data normalization only. Existing configured connections should not be downgraded.
    }
};
