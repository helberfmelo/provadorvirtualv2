<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'permissions')) {
            return;
        }

        DB::table('users')
            ->whereIn('role', ['admin', 'support'])
            ->whereNotNull('permissions')
            ->orderBy('id')
            ->each(function (object $user): void {
                $permissions = json_decode((string) $user->permissions, true);

                if (! is_array($permissions)) {
                    return;
                }

                $permissions['saas_checkout'] = [
                    'view' => true,
                    'edit' => true,
                ];

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['permissions' => json_encode($permissions)]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'permissions')) {
            return;
        }

        DB::table('users')
            ->whereIn('role', ['admin', 'support'])
            ->whereNotNull('permissions')
            ->orderBy('id')
            ->each(function (object $user): void {
                $permissions = json_decode((string) $user->permissions, true);

                if (! is_array($permissions)) {
                    return;
                }

                unset($permissions['saas_checkout']);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['permissions' => json_encode($permissions)]);
            });
    }
};
