<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke()
    {
        DB::select('select 1');

        return response()->json([
            'status' => 'ok',
            'service' => 'provador-virtual-api',
            'version' => config('app.version', '0.1.0-sprint1'),
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
