<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use App\Support\ActiveTenant;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserAccessController extends Controller
{
    public function saasIndex(Request $request): array
    {
        $this->ensureSaasPermission($request, 'view');

        $users = User::query()
            ->with(['merchants' => fn ($query) => $query->select('merchants.id', 'merchants.name', 'merchants.slug')])
            ->whereIn('role', ['admin', 'support'])
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        return [
            'data' => $users->map(fn (User $user): array => $this->serializeUser($user)),
            'meta' => $this->saasMeta(),
        ];
    }

    public function saasStore(Request $request)
    {
        $this->ensureSaasPermission($request, 'edit');

        $data = $this->validateUserPayload($request, allowSaasRole: true);
        $user = $this->saveUser($data, allowRole: true);

        if (filled($data['merchant_id'] ?? null)) {
            $merchant = Merchant::query()->findOrFail((int) $data['merchant_id']);
            $company = $this->companyForMerchant($merchant, $data['merchant_company_id'] ?? null);
            $this->syncMerchantAccess($user, $merchant, $company, $data);
        } elseif (in_array($user->role, ['admin', 'support'], true)) {
            $user->forceFill([
                'permissions' => PermissionCatalog::normalize($data['permissions'] ?? null, 'saas'),
            ])->save();
        }

        return response()->json([
            'data' => $this->serializeUser($user->fresh(['merchants']) ?? $user),
            'meta' => $this->saasMeta(),
        ], 201);
    }

    public function saasUpdate(Request $request, User $user)
    {
        $this->ensureSaasPermission($request, 'edit');

        $data = $this->validateUserPayload($request, updating: true, allowSaasRole: true);

        if ((int) $request->user()->id === (int) $user->id && ($data['status'] ?? null) === 'inactive') {
            throw new HttpException(422, 'Não desative seu próprio usuário SaaS.');
        }

        $user = $this->saveUser($data, $user, allowRole: true);

        if (filled($data['merchant_id'] ?? null)) {
            $merchant = Merchant::query()->findOrFail((int) $data['merchant_id']);
            $company = $this->companyForMerchant($merchant, $data['merchant_company_id'] ?? null);
            $this->syncMerchantAccess($user, $merchant, $company, $data);
        } elseif (array_key_exists('permissions', $data) && in_array($user->role, ['admin', 'support'], true)) {
            $user->forceFill([
                'permissions' => PermissionCatalog::normalize($data['permissions'] ?? null, 'saas'),
            ])->save();
        }

        return response()->json([
            'data' => $this->serializeUser($user->fresh(['merchants']) ?? $user),
            'meta' => $this->saasMeta(),
        ]);
    }

    public function saasCompanyUsersIndex(Request $request): array
    {
        $this->ensureSaasPermission($request, 'view', 'saas_company_users');

        $users = User::query()
            ->whereHas('merchants')
            ->with(['merchants' => fn ($query) => $query->select('merchants.id', 'merchants.name', 'merchants.slug')])
            ->orderByDesc('id')
            ->limit(200)
            ->get();
        $companyMap = $this->companyMapForUsers($users);

        return [
            'data' => $users->map(fn (User $user): array => $this->serializeUser($user, companiesById: $companyMap)),
            'meta' => $this->saasCompanyUsersMeta(),
        ];
    }

    public function saasCompanyUsersStore(Request $request)
    {
        $this->ensureSaasPermission($request, 'edit', 'saas_company_users');

        $data = $this->validateUserPayload($request, allowSaasRole: true);

        if (! filled($data['merchant_company_id'] ?? null)) {
            throw new HttpException(422, 'Selecione a empresa cliente para vincular o usuário.');
        }

        $company = MerchantCompany::query()
            ->with('merchant')
            ->findOrFail((int) $data['merchant_company_id']);
        $data['role'] = 'merchant';
        $data['merchant_id'] = $company->merchant_id;

        $user = $this->saveUser($data, allowRole: false);
        $this->syncMerchantAccess($user, $company->merchant, $company, $data);
        $companyMap = [$company->id => $this->serializeCompanyOption($company)];

        return response()->json([
            'data' => $this->serializeUser($user->fresh(['merchants']) ?? $user, companiesById: $companyMap),
            'meta' => $this->saasCompanyUsersMeta(),
        ], 201);
    }

    public function saasCompanyUsersUpdate(Request $request, User $user)
    {
        $this->ensureSaasPermission($request, 'edit', 'saas_company_users');

        $data = $this->validateUserPayload($request, updating: true, allowSaasRole: true);

        if ((int) $request->user()->id === (int) $user->id && ($data['status'] ?? null) === 'inactive') {
            throw new HttpException(422, 'Não desative seu próprio usuário SaaS.');
        }

        $company = $this->companyForCompanyUser($user, $data['merchant_company_id'] ?? null);
        $data['role'] = 'merchant';
        $data['merchant_id'] = $company->merchant_id;

        $user = $this->saveUser($data, $user, allowRole: false);
        $this->syncMerchantAccess($user, $company->merchant, $company, $data);
        $companyMap = [$company->id => $this->serializeCompanyOption($company)];

        return response()->json([
            'data' => $this->serializeUser($user->fresh(['merchants']) ?? $user, companiesById: $companyMap),
            'meta' => $this->saasCompanyUsersMeta(),
        ]);
    }

    public function merchantIndex(Request $request): array
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $this->ensureMerchantPermission($request, $merchant, 'view');

        $users = $merchant->users()
            ->orderBy('users.name')
            ->get();

        return [
            'data' => $users->map(fn (User $user): array => $this->serializeUser($user, $merchant)),
            'meta' => [
                'modules' => PermissionCatalog::merchantModules(),
            ],
        ];
    }

    public function merchantStore(Request $request)
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);
        $this->ensureMerchantPermission($request, $merchant, 'edit');

        $data = $this->validateUserPayload($request);
        $data['role'] = 'merchant';
        $user = $this->saveUser($data, allowRole: false);
        $this->syncMerchantAccess($user, $merchant, $company, $data);

        return response()->json([
            'data' => $this->serializeUser($user->fresh(['merchants']) ?? $user, $merchant),
            'meta' => [
                'modules' => PermissionCatalog::merchantModules(),
            ],
        ], 201);
    }

    public function merchantUpdate(Request $request, User $user)
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);
        $this->ensureMerchantPermission($request, $merchant, 'edit');

        if (! $user->merchants()->whereKey($merchant->id)->exists()) {
            throw new HttpException(404, 'Usuário não encontrado nesta empresa.');
        }

        $data = $this->validateUserPayload($request, updating: true);

        if ((int) $request->user()->id === (int) $user->id && ($data['merchant_user_status'] ?? null) === 'inactive') {
            throw new HttpException(422, 'Não desative seu próprio acesso à empresa.');
        }

        $data['role'] = $user->role;
        $user = $this->saveUser($data, $user, allowRole: false);
        $this->syncMerchantAccess($user, $merchant, $company, $data);

        return response()->json([
            'data' => $this->serializeUser($user->fresh(['merchants']) ?? $user, $merchant),
            'meta' => [
                'modules' => PermissionCatalog::merchantModules(),
            ],
        ]);
    }

    private function validateUserPayload(Request $request, bool $updating = false, bool $allowSaasRole = false): array
    {
        $merge = [];

        if ($request->has('email')) {
            $merge['email'] = mb_strtolower(trim((string) $request->input('email')));
        }

        if ($request->has('cpf')) {
            $merge['cpf'] = preg_replace('/\D+/', '', (string) $request->input('cpf')) ?: null;
        }

        $request->merge($merge);

        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$updating ? 'sometimes' : 'required', 'email', 'max:255'],
            'cpf' => ['sometimes', 'nullable', 'string', 'size:11'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'role' => [$allowSaasRole ? 'sometimes' : 'prohibited', 'string', 'in:admin,support,merchant'],
            'merchant_id' => [$allowSaasRole ? 'sometimes' : 'prohibited', 'nullable', 'integer', Rule::exists('merchants', 'id')],
            'merchant_company_id' => [$allowSaasRole ? 'sometimes' : 'prohibited', 'nullable', 'integer', Rule::exists('merchant_companies', 'id')],
            'merchant_role' => ['sometimes', 'string', 'in:owner,manager,staff'],
            'merchant_user_status' => ['sometimes', 'string', 'in:active,inactive'],
            'is_owner' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*.view' => ['sometimes', 'boolean'],
            'permissions.*.edit' => ['sometimes', 'boolean'],
        ]);
    }

    private function saveUser(array $data, ?User $target = null, bool $allowRole = false): User
    {
        $user = $target ?: $this->resolveUser($data);

        $payload = [];

        foreach (['name', 'email', 'cpf', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field] ?: null;
            }
        }

        if ($allowRole && array_key_exists('role', $data)) {
            $payload['role'] = $data['role'];
        } elseif (! $user->exists) {
            $payload['role'] = $data['role'] ?? 'merchant';
        }

        if (array_key_exists('password', $data) && filled($data['password'])) {
            $payload['password'] = Hash::make((string) $data['password']);
        } elseif (! $user->exists) {
            $payload['password'] = Hash::make(Str::random(32));
        }

        if (! $user->exists) {
            $payload['status'] ??= 'active';
        }

        $this->assertUniqueIdentity($data, $user);
        $user->forceFill($payload)->save();

        return $user;
    }

    private function resolveUser(array $data): User
    {
        $emailUser = filled($data['email'] ?? null)
            ? User::query()->where('email', $data['email'])->first()
            : null;
        $cpfUser = filled($data['cpf'] ?? null)
            ? User::query()->where('cpf', $data['cpf'])->first()
            : null;

        if ($emailUser && $cpfUser && (int) $emailUser->id !== (int) $cpfUser->id) {
            throw new HttpException(422, 'E-mail e CPF já pertencem a usuários diferentes.');
        }

        return $emailUser ?: $cpfUser ?: new User;
    }

    private function assertUniqueIdentity(array $data, User $user): void
    {
        if (filled($data['email'] ?? null)) {
            $exists = User::query()
                ->where('email', $data['email'])
                ->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))
                ->exists();

            if ($exists) {
                throw new HttpException(422, 'Este e-mail já está em uso.');
            }
        }

        if (filled($data['cpf'] ?? null)) {
            $exists = User::query()
                ->where('cpf', $data['cpf'])
                ->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))
                ->exists();

            if ($exists) {
                throw new HttpException(422, 'Este CPF já está em uso.');
            }
        }
    }

    private function syncMerchantAccess(User $user, Merchant $merchant, ?MerchantCompany $company, array $data): void
    {
        $existing = DB::table('merchant_user')
            ->where('merchant_id', $merchant->id)
            ->where('user_id', $user->id)
            ->first();

        $isOwner = array_key_exists('is_owner', $data)
            ? (bool) $data['is_owner']
            : (bool) ($existing->is_owner ?? false);
        $role = $data['merchant_role'] ?? $existing->role ?? ($isOwner ? 'owner' : 'staff');

        if ($role === 'owner') {
            $isOwner = true;
        }

        if ($isOwner) {
            $role = 'owner';
        }

        $permissions = array_key_exists('permissions', $data)
            ? PermissionCatalog::normalize($data['permissions'] ?? null, 'merchant')
            : (PermissionCatalog::decode($existing->permissions ?? null) ?: ($isOwner ? PermissionCatalog::full('merchant') : PermissionCatalog::empty('merchant')));

        if ($isOwner) {
            $permissions = PermissionCatalog::full('merchant');
        }

        $merchant->users()->syncWithoutDetaching([
            $user->id => [
                'merchant_company_id' => $company?->id ?: $existing?->merchant_company_id,
                'role' => $role,
                'status' => $data['merchant_user_status'] ?? $existing->status ?? 'active',
                'is_owner' => $isOwner,
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ],
        ]);
    }

    private function companyForMerchant(Merchant $merchant, mixed $companyId): ?MerchantCompany
    {
        if (! filled($companyId)) {
            return $merchant->companies()->orderBy('id')->first();
        }

        return $merchant->companies()->whereKey((int) $companyId)->firstOrFail();
    }

    private function companyForCompanyUser(User $user, mixed $companyId): MerchantCompany
    {
        if (filled($companyId)) {
            return MerchantCompany::query()->with('merchant')->findOrFail((int) $companyId);
        }

        $accessMerchant = $user->merchants()->first();

        if (! $accessMerchant) {
            throw new HttpException(404, 'Usuário não encontrado nas empresas clientes.');
        }

        if ($accessMerchant->pivot?->merchant_company_id) {
            return MerchantCompany::query()
                ->with('merchant')
                ->findOrFail((int) $accessMerchant->pivot->merchant_company_id);
        }

        return MerchantCompany::query()
            ->with('merchant')
            ->where('merchant_id', $accessMerchant->id)
            ->orderBy('id')
            ->firstOrFail();
    }

    private function ensureSaasPermission(Request $request, string $action, string $module = 'saas_users'): void
    {
        $user = $request->user();

        if (! $user || ($user->status ?? 'active') !== 'active' || ! PermissionCatalog::canSaas($user, $module, $action)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }

    private function ensureMerchantPermission(Request $request, Merchant $merchant, string $action): void
    {
        if (! PermissionCatalog::canMerchant($request->user(), $merchant, 'users', $action)) {
            throw new HttpException(403, 'Sem permissão para gerenciar usuários.');
        }
    }

    private function serializeUser(User $user, ?Merchant $merchantContext = null, ?array $companiesById = null): array
    {
        $user->loadMissing('merchants');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => $user->cpf,
            'role' => $user->role,
            'status' => $user->status ?? 'active',
            'permissions' => PermissionCatalog::forSaasUser($user),
            'access' => $merchantContext ? $this->serializeAccess($user, $merchantContext) : null,
            'merchants' => $user->merchants->map(fn (Merchant $merchant): array => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'slug' => $merchant->slug,
                'access' => $this->serializePivotAccess($merchant->pivot, $companiesById),
            ])->values(),
        ];
    }

    private function serializeAccess(User $user, Merchant $merchant): ?array
    {
        $accessMerchant = $user->merchants->firstWhere('id', $merchant->id)
            ?: $user->merchants()->whereKey($merchant->id)->first();

        return $accessMerchant ? $this->serializePivotAccess($accessMerchant->pivot) : null;
    }

    private function serializePivotAccess(mixed $pivot, ?array $companiesById = null): array
    {
        $isOwner = (bool) ($pivot->is_owner ?? false) || ($pivot->role ?? null) === 'owner';
        $companyId = $pivot->merchant_company_id ? (int) $pivot->merchant_company_id : null;

        return [
            'merchant_id' => (int) ($pivot->merchant_id ?? 0),
            'merchant_company_id' => $companyId,
            'company' => $companyId && $companiesById ? ($companiesById[$companyId] ?? null) : null,
            'role' => $pivot->role ?? 'staff',
            'status' => $pivot->status ?? 'active',
            'is_owner' => $isOwner,
            'permissions' => $isOwner
                ? PermissionCatalog::full('merchant')
                : PermissionCatalog::normalize(PermissionCatalog::decode($pivot->permissions ?? null), 'merchant'),
        ];
    }

    private function companyMapForUsers($users): array
    {
        $ids = [];

        foreach ($users as $user) {
            foreach ($user->merchants as $merchant) {
                if ($merchant->pivot?->merchant_company_id) {
                    $ids[] = (int) $merchant->pivot->merchant_company_id;
                }
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return MerchantCompany::query()
            ->with('merchant')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (MerchantCompany $company): array => [
                $company->id => $this->serializeCompanyOption($company),
            ])
            ->all();
    }

    private function saasMeta(): array
    {
        return [
            'saas_modules' => PermissionCatalog::saasModules(),
            'merchant_modules' => PermissionCatalog::merchantModules(),
            'merchants' => Merchant::query()
                ->select('id', 'name', 'slug')
                ->orderBy('name')
                ->limit(120)
                ->get(),
        ];
    }

    private function saasCompanyUsersMeta(): array
    {
        return [
            'saas_modules' => PermissionCatalog::saasModules(),
            'merchant_modules' => PermissionCatalog::merchantModules(),
            'companies' => MerchantCompany::query()
                ->with('merchant')
                ->orderBy('name')
                ->limit(300)
                ->get()
                ->map(fn (MerchantCompany $company): array => $this->serializeCompanyOption($company))
                ->values(),
        ];
    }

    private function serializeCompanyOption(MerchantCompany $company): array
    {
        $company->loadMissing('merchant');

        return [
            'id' => $company->id,
            'access_code' => $company->access_code,
            'name' => $company->name,
            'document' => $company->document,
            'platform' => $company->platform,
            'status' => $company->status,
            'merchant' => [
                'id' => $company->merchant?->id,
                'name' => $company->merchant?->name,
                'slug' => $company->merchant?->slug,
            ],
        ];
    }
}
