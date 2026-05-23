<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ResolvesMerchant;

    public function index(Request $request): array
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->limit(50);

        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            $merchant = $this->currentMerchant($request);
            $query->where('merchant_id', $merchant->id);
        } elseif ($request->integer('merchant_id')) {
            $query->where('merchant_id', $request->integer('merchant_id'));
        }

        return [
            'data' => $query->get()->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'merchant_id' => $log->merchant_id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'event' => $log->event,
                'category' => $log->category,
                'severity' => $log->severity,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toISOString(),
            ]),
        ];
    }
}
