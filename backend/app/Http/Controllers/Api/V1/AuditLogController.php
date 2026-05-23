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
            ->with(['user:id,name,email', 'company:id,name,access_code'])
            ->orderByDesc('id')
            ->limit(50);

        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            $merchant = $this->currentMerchant($request);
            $company = $this->currentCompany($request, $merchant);
            $query->where('merchant_id', $merchant->id);
            $this->scopeCompany($query, $company);
        } elseif ($request->integer('merchant_id')) {
            $query->where('merchant_id', $request->integer('merchant_id'));
        }

        return [
            'data' => $query->get()->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'merchant_id' => $log->merchant_id,
                'merchant_company_id' => $log->merchant_company_id,
                'company' => $log->company ? [
                    'id' => $log->company->id,
                    'name' => $log->company->name,
                    'access_code' => $log->company->access_code,
                ] : null,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'event' => $log->event,
                'category' => $log->category,
                'module' => $log->module,
                'action' => $log->action,
                'severity' => $log->severity,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toISOString(),
            ]),
        ];
    }
}
