<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogger;
use App\Support\ActiveTenant;
use App\Support\PermissionCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsurePortalPermission
{
    public function handle(Request $request, Closure $next, string $scope, string $module, string $action = 'view')
    {
        $user = $request->user();

        if (! $user || ($user->status ?? 'active') !== 'active') {
            throw new HttpException(403, 'Usuario sem permissao para acessar este recurso.');
        }

        if ($scope === 'saas') {
            $allowed = PermissionCatalog::canSaas($user, $module, $action);

            if (! $allowed) {
                app(AuditLogger::class)->log($request, null, 'permission.denied', 'permissions', 'warning', [
                    'scope' => 'saas',
                    'module' => $module,
                    'action' => $action,
                ]);

                throw new HttpException(403, 'Sem permissao para acessar este modulo SaaS.');
            }

            return $next($request);
        }

        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);
        $allowed = PermissionCatalog::canMerchant($user, $merchant, $module, $action);

        if (! $allowed) {
            app(AuditLogger::class)->log($request, $merchant, 'permission.denied', 'permissions', 'warning', [
                'merchant_company_id' => $company?->id,
                'scope' => 'merchant',
                'module' => $module,
                'action' => $action,
            ]);

            throw new HttpException(403, 'Sem permissao para acessar este modulo.');
        }

        return $next($request);
    }
}
