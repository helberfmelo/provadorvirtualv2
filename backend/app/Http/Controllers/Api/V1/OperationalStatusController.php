<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class OperationalStatusController extends Controller
{
    public function __invoke()
    {
        $checks = [
            'database' => $this->databaseStatus(),
            'storage' => is_writable(storage_path('framework')) ? 'ok' : 'failed',
            'queue' => config('queue.default', 'sync'),
        ];

        $status = in_array('failed', $checks, true) ? 'degraded' : 'ok';

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'app_env' => app()->environment(),
            'timestamp' => now()->toISOString(),
        ], $status === 'ok' ? 200 : 503);
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (\Throwable) {
            return 'failed';
        }
    }
}
