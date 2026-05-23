<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\ImportJob;
use App\Models\IntegrationEvent;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\RecommendationLog;
use App\Models\User;
use App\Models\WidgetInstall;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaasAdminController extends Controller
{
    public function overview(Request $request): array
    {
        $this->ensureAdmin($request);

        return [
            'data' => [
                'summary' => [
                    'merchants' => Merchant::query()->count(),
                    'trialing_merchants' => Merchant::query()->where('billing_status', 'trialing')->count(),
                    'companies' => MerchantCompany::query()->count(),
                    'products' => Product::query()->count(),
                    'active_widgets' => WidgetInstall::query()->where('is_active', true)->count(),
                    'configured_integrations' => PlatformConnection::query()->whereIn('status', ['configured', 'connected'])->count(),
                    'recommendations_7d' => RecommendationLog::query()->where('created_at', '>=', now()->subDays(7))->count(),
                    'ai_uses_7d' => AiUsageLog::query()->where('created_at', '>=', now()->subDays(7))->count(),
                    'failed_imports_7d' => ImportJob::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
                    'failed_integrations_7d' => IntegrationEvent::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
                ],
            ],
        ];
    }

    public function merchants(Request $request): array
    {
        $this->ensureAdmin($request);

        $merchants = Merchant::query()
            ->withCount(['companies', 'products', 'measurementTables', 'widgetInstalls', 'platformConnections'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'data' => $merchants->map(fn (Merchant $merchant): array => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'slug' => $merchant->slug,
                'billing_status' => $merchant->billing_status,
                'trial_ends_at' => $merchant->trial_ends_at?->toDateString(),
                'companies_count' => $merchant->companies_count,
                'products_count' => $merchant->products_count,
                'measurement_tables_count' => $merchant->measurement_tables_count,
                'widget_installs_count' => $merchant->widget_installs_count,
                'platform_connections_count' => $merchant->platform_connections_count,
                'recommendations_7d' => RecommendationLog::query()
                    ->where('merchant_id', $merchant->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'last_recommendation_at' => RecommendationLog::query()
                    ->where('merchant_id', $merchant->id)
                    ->latest('id')
                    ->first()?->created_at?->toISOString(),
            ]),
        ];
    }

    public function companies(Request $request): array
    {
        $this->ensureAdmin($request);

        $companies = MerchantCompany::query()
            ->with('merchant')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        return [
            'data' => $companies->map(fn (MerchantCompany $company): array => $this->serializeCompany($company)),
        ];
    }

    public function storeCompany(Request $request)
    {
        $this->ensureAdmin($request);

        $data = $this->validateCompanyPayload($request);
        $merchant = filled($data['merchant_id'] ?? null)
            ? Merchant::query()->findOrFail((int) $data['merchant_id'])
            : Merchant::query()->create([
                'name' => $data['merchant_name'] ?: $data['name'],
                'slug' => $this->uniqueMerchantSlug($data['merchant_name'] ?: $data['name']),
                'billing_status' => $data['billing_status'] ?? 'trialing',
                'trial_ends_at' => ($data['billing_status'] ?? 'trialing') === 'trialing'
                    ? now()->addDays(14)
                    : null,
            ]);

        if (filled($data['billing_status'] ?? null)) {
            $merchant->forceFill(['billing_status' => $data['billing_status']])->save();
        }

        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'document' => $data['document'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'street' => $data['street'] ?? null,
            'number' => $data['number'] ?? null,
            'complement' => $data['complement'] ?? null,
            'district' => $data['district'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => 'BR',
            'domain' => $data['domain'] ?? null,
            'platform' => $data['platform'] ?? 'custom',
            'external_store_id' => $data['external_store_id'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
        $company->ensureAccessCode();

        $owner = $this->upsertOwnerIfRequested($merchant, $data);
        $this->createWidgetInstallForCompany($merchant, $company);

        return response()->json([
            'data' => [
                ...$this->serializeCompany($company->fresh(['merchant']) ?? $company),
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'role' => $owner->role,
                ] : null,
            ],
        ], 201);
    }

    public function updateCompany(Request $request, MerchantCompany $company)
    {
        $this->ensureAdmin($request);

        $data = $this->validateCompanyPayload($request, updating: true);

        $company->forceFill([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'document' => $data['document'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'street' => $data['street'] ?? null,
            'number' => $data['number'] ?? null,
            'complement' => $data['complement'] ?? null,
            'district' => $data['district'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => 'BR',
            'domain' => $data['domain'] ?? null,
            'platform' => $data['platform'] ?? 'custom',
            'external_store_id' => $data['external_store_id'] ?? null,
            'status' => $data['status'] ?? 'active',
        ])->save();

        $company->ensureAccessCode();

        return response()->json([
            'data' => $this->serializeCompany($company->fresh(['merchant']) ?? $company),
        ]);
    }

    public function resolveCompanyAccess(Request $request)
    {
        $data = $request->validate([
            'code_or_document' => ['required', 'string', 'max:32'],
        ]);

        $value = trim((string) $data['code_or_document']);
        $digits = preg_replace('/\D+/', '', $value) ?: $value;

        $company = MerchantCompany::query()
            ->with('merchant')
            ->where(function ($query) use ($value, $digits): void {
                $query->where('access_code', $value)
                    ->orWhere('access_code', $digits)
                    ->orWhere('document', $digits);
            })
            ->first();

        abort_if(! $company, 404, 'Empresa nao encontrada para o codigo ou documento informado.');

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'access_code' => $company->access_code,
                'status' => $company->status,
                'platform' => $company->platform,
                'merchant' => [
                    'id' => $company->merchant?->id,
                    'name' => $company->merchant?->name,
                    'billing_status' => $company->merchant?->billing_status,
                ],
            ],
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }

    private function validateCompanyPayload(Request $request, bool $updating = false): array
    {
        $request->merge([
            'document' => preg_replace('/\D+/', '', (string) $request->input('document')) ?: null,
            'zip_code' => preg_replace('/\D+/', '', (string) $request->input('zip_code')) ?: null,
            'owner_email' => filled($request->input('owner_email'))
                ? mb_strtolower(trim((string) $request->input('owner_email')))
                : null,
            'owner_cpf' => preg_replace('/\D+/', '', (string) $request->input('owner_cpf')) ?: null,
            'state' => filled($request->input('state'))
                ? mb_strtoupper(trim((string) $request->input('state')))
                : null,
        ]);

        return $request->validate([
            'merchant_id' => [$updating ? 'prohibited' : 'nullable', 'integer', Rule::exists('merchants', 'id')],
            'merchant_name' => [$updating ? 'nullable' : 'required_without:merchant_id', 'nullable', 'string', 'max:255'],
            'billing_status' => ['nullable', 'string', 'in:trialing,active,pending_payment,past_due,canceled'],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:18'],
            'zip_code' => ['nullable', 'string', 'size:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:40'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'domain' => ['nullable', 'string', 'max:180'],
            'platform' => ['nullable', 'string', Rule::in(PlatformCatalog::keys())],
            'external_store_id' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:active,inactive,pending_payment,trialing'],
            'owner_name' => ['nullable', 'string', 'max:255', 'required_with:owner_email'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_cpf' => ['nullable', 'string', 'size:11'],
            'owner_password' => ['nullable', 'string', 'min:8'],
        ]);
    }

    private function upsertOwnerIfRequested(Merchant $merchant, array $data): ?User
    {
        if (blank($data['owner_email'] ?? null)) {
            return null;
        }

        $payload = [
            'name' => $data['owner_name'] ?: $data['name'],
            'cpf' => $data['owner_cpf'] ?? null,
            'role' => 'merchant',
        ];

        if (filled($data['owner_password'] ?? null)) {
            $payload['password'] = Hash::make((string) $data['owner_password']);
        }

        $emailUser = User::query()->where('email', $data['owner_email'])->first();
        $cpfUser = filled($data['owner_cpf'] ?? null)
            ? User::query()->where('cpf', $data['owner_cpf'])->first()
            : null;

        if ($emailUser && $cpfUser && (int) $emailUser->id !== (int) $cpfUser->id) {
            throw new HttpException(422, 'E-mail e CPF ja pertencem a usuarios diferentes.');
        }

        $owner = $emailUser ?: $cpfUser ?: new User;
        $payload['email'] = $owner->exists ? $owner->email : $data['owner_email'];

        if ($owner->exists && $owner->email !== $data['owner_email']) {
            $emailInUse = User::query()
                ->where('email', $data['owner_email'])
                ->whereKeyNot($owner->id)
                ->exists();

            if (! $emailInUse) {
                $payload['email'] = $data['owner_email'];
            }
        }

        if (! $owner->exists && blank($payload['password'] ?? null)) {
            $payload['password'] = Hash::make(Str::random(18));
        }

        $owner->forceFill($payload)->save();
        $owner->merchants()->syncWithoutDetaching([$merchant->id]);

        return $owner;
    }

    private function createWidgetInstallForCompany(Merchant $merchant, MerchantCompany $company): void
    {
        WidgetInstall::query()->firstOrCreate(
            [
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company->id,
            ],
            [
                'public_key' => 'pv_'.Str::lower(Str::random(24)),
                'platform' => $company->platform ?: 'custom',
                'allowed_domains' => array_values(array_filter([
                    $company->domain,
                    'provadorvirtual.online',
                    'localhost',
                    '127.0.0.1',
                ])),
                'theme' => $this->defaultWidgetTheme(),
                'is_active' => true,
            ],
        );
    }

    private function serializeCompany(MerchantCompany $company): array
    {
        return [
            'id' => $company->id,
            'access_code' => $company->access_code,
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'document' => $company->document,
            'zip_code' => $company->zip_code,
            'street' => $company->street,
            'number' => $company->number,
            'complement' => $company->complement,
            'district' => $company->district,
            'city' => $company->city,
            'state' => $company->state,
            'country' => $company->country,
            'domain' => $company->domain,
            'platform' => $company->platform,
            'external_store_id' => $company->external_store_id,
            'status' => $company->status,
            'merchant' => [
                'id' => $company->merchant?->id,
                'name' => $company->merchant?->name,
                'slug' => $company->merchant?->slug,
                'billing_status' => $company->merchant?->billing_status,
            ],
            'created_at' => $company->created_at?->toISOString(),
        ];
    }

    private function uniqueMerchantSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'lojista';
        $slug = $base;
        $counter = 2;

        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function defaultWidgetTheme(): array
    {
        return [
            'primary' => '#0f172a',
            'secondary' => '#ff4d5e',
            'accent' => '#ff7a1a',
            'background' => '#ffffff',
            'text' => '#111827',
            'font_family' => 'Manrope, Inter, Arial, sans-serif',
            'font_size' => '14',
            'font_weight' => '800',
            'button_radius' => '8',
        ];
    }
}
