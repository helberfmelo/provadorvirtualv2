<?php

namespace App\Http\Middleware;

use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\RecommendationLog;
use App\Models\WidgetInstall;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWidgetOriginAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($request->isMethod('OPTIONS')) {
            return $this->withCorsHeaders(response()->noContent(), $origin);
        }

        if (! $origin) {
            return $next($request);
        }

        $originHost = $this->hostFromOrigin($origin);
        $merchantId = $this->merchantId($request);

        if (! $originHost || ! $merchantId || ! $this->isAllowed($merchantId, $originHost, $this->companyId($request))) {
            return response()->json([
                'message' => 'Origem não autorizada para este widget.',
            ], 403);
        }

        return $this->withCorsHeaders($next($request), $origin);
    }

    private function merchantId(Request $request): ?int
    {
        $merchantId = $request->input('merchant_id');

        if (is_numeric($merchantId) && (int) $merchantId > 0) {
            return (int) $merchantId;
        }

        return $this->recommendationLog($request)?->merchant_id
            ?? $this->bigShopCompany($request)?->merchant_id;
    }

    private function companyId(Request $request): ?int
    {
        $bigShopCompany = $this->bigShopCompany($request);

        if ($bigShopCompany) {
            return (int) $bigShopCompany->id;
        }

        $companyId = $request->input('store_id');

        if (is_numeric($companyId) && (int) $companyId > 0) {
            return (int) $companyId;
        }

        return $this->recommendationLog($request)?->merchant_company_id;
    }

    private function bigShopCompany(Request $request): ?MerchantCompany
    {
        if (mb_strtolower((string) $request->input('platform')) !== 'bigshop') {
            return null;
        }

        $storeId = $request->input('store_id');

        if (! is_numeric($storeId)) {
            return null;
        }

        $company = MerchantCompany::query()
            ->where('platform', 'bigshop')
            ->where('external_store_id', (string) $storeId)
            ->first();

        if ($company) {
            return $company;
        }

        return PlatformConnection::query()
            ->with('company')
            ->where('platform', 'bigshop')
            ->where('external_store_id', (string) $storeId)
            ->whereNotNull('merchant_company_id')
            ->first()?->company;
    }

    private function recommendationLog(Request $request): ?RecommendationLog
    {
        $routeValue = $request->route('recommendationLog');

        if ($routeValue instanceof RecommendationLog) {
            return $routeValue;
        }

        if (is_numeric($routeValue)) {
            return RecommendationLog::query()->find((int) $routeValue);
        }

        return null;
    }

    private function isAllowed(int $merchantId, string $originHost, ?int $companyId): bool
    {
        return WidgetInstall::query()
            ->where('merchant_id', $merchantId)
            ->where('is_active', true)
            ->when($companyId, function ($query, int $companyId): void {
                $query->where(function ($query) use ($companyId): void {
                    $query->where('merchant_company_id', $companyId)
                        ->orWhereNull('merchant_company_id');
                });
            })
            ->get()
            ->contains(function (WidgetInstall $install) use ($originHost): bool {
                return collect($install->allowed_domains ?? [])
                    ->map(fn (string $domain): ?string => $this->normalizeDomain($domain))
                    ->filter()
                    ->contains(fn (string $domain): bool => $this->hostMatches($originHost, $domain));
            });
    }

    private function hostFromOrigin(string $origin): ?string
    {
        $host = parse_url($origin, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return mb_strtolower($host);
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = trim($domain);

        if ($domain === '') {
            return null;
        }

        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return mb_strtolower(ltrim($host, '*.'));
    }

    private function hostMatches(string $originHost, string $allowedDomain): bool
    {
        return $originHost === $allowedDomain || str_ends_with($originHost, '.'.$allowedDomain);
    }

    private function withCorsHeaders(Response $response, ?string $origin): Response
    {
        if (! $origin) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '600');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
