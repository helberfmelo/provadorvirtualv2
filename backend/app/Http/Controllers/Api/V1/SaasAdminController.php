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
use App\Services\TransactionalEmailService;
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
            'bigshop_discount_active' => $this->bigShopDiscountState($data['platform'] ?? 'custom', $data, true),
            'external_store_id' => $data['external_store_id'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
        $company->ensureAccessCode();

        $owner = $this->upsertOwnerIfRequested($merchant, $data);
        $this->createWidgetInstallForCompany($merchant, $company);

        if ($owner) {
            app(TransactionalEmailService::class)->sendForCompany(
                TransactionalEmailService::CODE_SIGNUP,
                $company->fresh(['merchant']) ?? $company,
                $owner,
            );
        }

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
            'bigshop_discount_active' => $this->bigShopDiscountState($data['platform'] ?? 'custom', $data, (bool) $company->bigshop_discount_active),
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

        abort_if(! $company, 404, 'Empresa não encontrada para o código ou documento informado.');

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
            'bigshop_discount_active' => ['nullable', 'boolean'],
            'external_store_id' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:active,inactive,pending_payment,trialing'],
            'owner_name' => ['nullable', 'string', 'max:255', 'required_with:owner_email'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_cpf' => ['nullable', 'string', 'size:11'],
            'owner_password' => ['nullable', 'string', 'min:8'],
        ]);
    }

    private function bigShopDiscountState(string $platform, array $data, bool $default): bool
    {
        if ($platform !== 'bigshop') {
            return false;
        }

        return array_key_exists('bigshop_discount_active', $data)
            ? (bool) $data['bigshop_discount_active']
            : $default;
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
            throw new HttpException(422, 'E-mail e CPF já pertencem a usuários diferentes.');
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
            'bigshop_discount_active' => (bool) $company->bigshop_discount_active,
            'external_store_id' => $company->external_store_id,
            'status' => $company->status,
            'integration_state' => $this->companyIntegrationState($company),
            'merchant' => [
                'id' => $company->merchant?->id,
                'name' => $company->merchant?->name,
                'slug' => $company->merchant?->slug,
                'billing_status' => $company->merchant?->billing_status,
            ],
            'created_at' => $company->created_at?->toISOString(),
        ];
    }

    private function companyIntegrationState(MerchantCompany $company): array
    {
        $platform = $company->platform ?: 'custom';
        $entry = PlatformCatalog::find($platform) ?: PlatformCatalog::find('custom');
        $connections = PlatformConnection::query()
            ->where('merchant_id', $company->merchant_id)
            ->where(function ($query) use ($company): void {
                $query->where('merchant_company_id', $company->id)
                    ->orWhereNull('merchant_company_id');
            })
            ->orderByRaw('merchant_company_id is null')
            ->latest('id')
            ->get();
        $primary = $connections->firstWhere('platform', $platform) ?: $connections->first();
        $technicalStatus = $this->technicalStatusFor($primary);
        $commercialStatus = $this->commercialStatusFor($company);

        return [
            'platform' => $platform,
            'platform_label' => $entry['name'] ?? $platform,
            'technical_status' => $technicalStatus,
            'technical_label' => $this->technicalStatusLabel($technicalStatus),
            'commercial_status' => $commercialStatus,
            'commercial_label' => $this->commercialStatusLabel($commercialStatus),
            'connections_count' => $connections->count(),
            'has_feed_url' => $connections->contains(fn (PlatformConnection $connection): bool => filled($connection->feed_url)),
            'has_api_credentials' => $connections->contains(fn (PlatformConnection $connection): bool => filled($connection->api_base_url) && filled($connection->access_token_encrypted)),
            'has_webhook_secret' => $connections->contains(fn (PlatformConnection $connection): bool => filled($connection->webhook_secret_encrypted)),
            'last_sync_at' => $connections
                ->pluck('last_sync_at')
                ->filter()
                ->sortDesc()
                ->first()?->toISOString(),
            'last_error' => $primary?->last_error,
        ];
    }

    private function technicalStatusFor(?PlatformConnection $connection): string
    {
        if (! $connection) {
            return 'missing';
        }

        if (in_array($connection->status, ['connected', 'disabled', 'error'], true)) {
            return $connection->status;
        }

        $hasStore = filled($connection->external_store_id);
        $hasFeed = filled($connection->feed_url);
        $hasApi = filled($connection->api_base_url) && filled($connection->access_token_encrypted);

        return ($hasStore || $hasFeed || $hasApi) ? 'configured' : ($connection->status ?: 'draft');
    }

    private function technicalStatusLabel(string $status): string
    {
        return [
            'missing' => 'Sem conexão',
            'draft' => 'Pendente',
            'configured' => 'Configurada',
            'connected' => 'Conectada',
            'disabled' => 'Pausada',
            'error' => 'Erro técnico',
        ][$status] ?? $status;
    }

    private function commercialStatusFor(MerchantCompany $company): string
    {
        if ($company->platform === 'bigshop' && $company->bigshop_discount_active) {
            return 'bigshop_benefit';
        }

        return $company->merchant?->billing_status ?: $company->status;
    }

    private function commercialStatusLabel(string $status): string
    {
        return [
            'bigshop_benefit' => 'Benefício BigShop',
            'trialing' => 'Trial',
            'active' => 'Comercial ativo',
            'pending_payment' => 'Pagamento pendente',
            'past_due' => 'Em atraso',
            'canceled' => 'Cancelado',
            'inactive' => 'Empresa inativa',
        ][$status] ?? $status;
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
            'button_style' => 'gallery_1_text_icons',
            'button_background' => '#ff4d5e',
            'button_text' => '#ffffff',
            'button_primary_icon' => 'hanger',
            'button_secondary_icon' => 'ruler',
            'button_icon_animation' => true,
            'confetti_enabled' => true,
            'presentation_mode' => 'drawer',
        ];
    }
}
