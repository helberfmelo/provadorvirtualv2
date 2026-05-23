<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\ActiveTenant;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'string', 'max:255', 'required_without:login'],
            'password' => ['required', 'string'],
            'company_access' => ['nullable', 'string', 'max:32'],
        ]);

        $identifier = trim((string) ($credentials['login'] ?? $credentials['email'] ?? ''));
        $user = $this->findUserByIdentifier($identifier);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Login ou senha invalidos.'],
            ]);
        }

        if (($user->status ?? 'active') !== 'active') {
            throw ValidationException::withMessages([
                'login' => ['Este usuario esta inativo. Fale com o administrador.'],
            ]);
        }

        if (! in_array($user->role, ['admin', 'support'], true)
            && ! filled($request->input('company_access'))
            && count($this->companyOptionsFor($user)) > 1) {
            return response()->json([
                'message' => 'Selecione a empresa para acessar o portal.',
                'company_options' => $this->companyOptionsFor($user),
            ], 409);
        }

        [$merchant, $company] = $this->resolveLoginContext($user, $request);
        $token = $this->issueToken($user, $merchant, $company);

        app(AuditLogger::class)->log($request, $merchant, 'auth.login', 'auth', 'info', [
            'email' => $user->email,
            'company_access' => $company?->access_code,
        ], actor: $user);

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
            'active_merchant' => $merchant ? $this->serializeMerchant($merchant) : null,
            'active_company' => $company ? $this->serializeCompany($company) : null,
            'permissions' => $merchant ? PermissionCatalog::forMerchantUser($user, $merchant) : null,
            'saas_permissions' => PermissionCatalog::forSaasUser($user),
            'company_options' => $this->companyOptionsFor($user),
        ]);
    }

    public function logout(Request $request)
    {
        $merchant = $this->safeActiveMerchant($request);

        app(AuditLogger::class)->log($request, $merchant, 'auth.logout', 'auth', 'info');

        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sessao encerrada com sucesso.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $merchant = $this->safeActiveMerchant($request);
        $company = $merchant ? app(ActiveTenant::class)->company($request, $merchant) : null;

        return response()->json([
            'user' => $this->serializeUser($user),
            'active_merchant' => $merchant ? $this->serializeMerchant($merchant) : null,
            'active_company' => $company ? $this->serializeCompany($company) : null,
            'permissions' => $merchant ? PermissionCatalog::forMerchantUser($user, $merchant) : null,
            'saas_permissions' => PermissionCatalog::forSaasUser($user),
            'company_options' => $this->companyOptionsFor($user),
            'merchants' => $user->merchants()
                ->when(! in_array($user->role, ['admin', 'support'], true), fn ($query) => $query->where(function ($innerQuery): void {
                    $innerQuery->where('merchant_user.status', 'active')
                        ->orWhereNull('merchant_user.status');
                }))
                ->select('merchants.id', 'merchants.name', 'merchants.slug', 'merchants.billing_status')
                ->get(),
        ]);
    }

    public function selectCompany(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'company_access' => ['nullable', 'string', 'max:32', 'required_without:company_id'],
        ]);

        $company = filled($data['company_id'] ?? null)
            ? MerchantCompany::query()->with('merchant')->findOrFail((int) $data['company_id'])
            : $this->resolveCompanyByAccess((string) $data['company_access']);
        $merchant = $company->merchant;
        $user = $request->user();

        if (! $this->canAccessCompany($user, $company)) {
            throw ValidationException::withMessages([
                'company_access' => ['Este usuario nao pertence a empresa informada.'],
            ]);
        }

        $request->user()?->currentAccessToken()?->delete();
        $token = $this->issueToken($user, $merchant, $company);

        app(AuditLogger::class)->log($request, $merchant, 'auth.company_selected', 'auth', 'info', [
            'merchant_company_id' => $company->id,
            'module' => 'auth',
            'action' => 'select_company',
        ], actor: $user);

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
            'active_merchant' => $this->serializeMerchant($merchant),
            'active_company' => $this->serializeCompany($company),
            'permissions' => PermissionCatalog::forMerchantUser($user, $merchant),
            'saas_permissions' => PermissionCatalog::forSaasUser($user),
            'company_options' => $this->companyOptionsFor($user),
        ]);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        $email = mb_strtolower($identifier);
        $cpf = preg_replace('/\D+/', '', $identifier) ?: null;

        return User::query()
            ->where('email', $email)
            ->when($cpf && strlen($cpf) === 11, fn ($query) => $query->orWhere('cpf', $cpf))
            ->first();
    }

    private function resolveLoginContext(User $user, Request $request): array
    {
        $companyAccess = trim((string) $request->input('company_access'));

        if ($companyAccess !== '') {
            $company = $this->resolveCompanyByAccess($companyAccess);
            $merchant = $company->merchant;

            if (! $this->canAccessCompany($user, $company)) {
                throw ValidationException::withMessages([
                    'company_access' => ['Este usuario nao pertence a empresa informada.'],
                ]);
            }

            return [$merchant, $company];
        }

        if (in_array($user->role, ['admin', 'support'], true)) {
            $merchant = $user->merchants()->orderBy('merchants.id')->first();

            return [$merchant, $merchant?->companies()->orderBy('id')->first()];
        }

        $merchantIds = $user->merchants()
            ->where(function ($query): void {
                $query->where('merchant_user.status', 'active')
                    ->orWhereNull('merchant_user.status');
            })
            ->pluck('merchants.id');
        $companyCount = count($this->companyOptionsFor($user));

        if ($companyCount > 1) {
            throw ValidationException::withMessages([
                'company_access' => ['Informe o codigo da loja ou CNPJ para entrar no portal da empresa.'],
            ]);
        }

        $merchant = $user->merchants()
            ->where(function ($query): void {
                $query->where('merchant_user.status', 'active')
                    ->orWhereNull('merchant_user.status');
            })
            ->orderBy('merchants.id')
            ->first();
        if (! $merchant) {
            throw ValidationException::withMessages([
                'company_access' => ['Usuario sem empresa vinculada.'],
            ]);
        }

        return [$merchant, $merchant->companies()->orderBy('id')->first()];
    }

    private function resolveCompanyByAccess(string $value): MerchantCompany
    {
        $digits = preg_replace('/\D+/', '', $value) ?: $value;

        $company = MerchantCompany::query()
            ->with('merchant')
            ->where(function ($query) use ($value, $digits): void {
                $query->where('access_code', $value)
                    ->orWhere('access_code', $digits)
                    ->orWhere('document', $digits);
            })
            ->first();

        if (! $company) {
            throw ValidationException::withMessages([
                'company_access' => ['Empresa nao encontrada para o codigo ou CNPJ informado.'],
            ]);
        }

        return $company;
    }

    private function canAccessMerchant(User $user, ?Merchant $merchant): bool
    {
        if (! $merchant) {
            return false;
        }

        if (in_array($user->role, ['admin', 'support'], true)) {
            return true;
        }

        return $user->merchants()
            ->whereKey($merchant->id)
            ->where(function ($query): void {
                $query->where('merchant_user.status', 'active')
                    ->orWhereNull('merchant_user.status');
            })
            ->exists();
    }

    private function canAccessCompany(User $user, ?MerchantCompany $company): bool
    {
        if (! $company || ! $company->merchant) {
            return false;
        }

        if (($company->status ?? 'active') === 'inactive') {
            return false;
        }

        if (in_array($user->role, ['admin', 'support'], true)) {
            return true;
        }

        $merchant = $user->merchants()
            ->whereKey($company->merchant_id)
            ->where(function ($query): void {
                $query->where('merchant_user.status', 'active')
                    ->orWhereNull('merchant_user.status');
            })
            ->first();

        if (! $merchant) {
            return false;
        }

        $pivotCompanyId = $merchant->pivot?->merchant_company_id;

        return ! $pivotCompanyId || (int) $pivotCompanyId === (int) $company->id;
    }

    private function issueToken(User $user, ?Merchant $merchant, ?MerchantCompany $company): string
    {
        $abilities = ['role:'.$user->role];

        if ($merchant) {
            $abilities[] = 'merchant:'.$merchant->id;
        }

        if ($company) {
            $abilities[] = 'company:'.$company->id;
        }

        return $user->createToken('provadorvirtual-spa', $abilities)->plainTextToken;
    }

    private function companyOptionsFor(User $user): array
    {
        if (in_array($user->role, ['admin', 'support'], true)) {
            return MerchantCompany::query()
                ->with('merchant:id,name,slug,billing_status')
                ->orderBy('name')
                ->limit(100)
                ->get()
                ->map(fn (MerchantCompany $company): array => $this->serializeCompanyOption($company))
                ->values()
                ->all();
        }

        $merchants = $user->merchants()
            ->where(function ($query): void {
                $query->where('merchant_user.status', 'active')
                    ->orWhereNull('merchant_user.status');
            })
            ->get();

        return $merchants
            ->flatMap(function (Merchant $merchant): array {
                $companyId = $merchant->pivot?->merchant_company_id;
                $query = $merchant->companies()
                    ->where('status', '!=', 'inactive')
                    ->orderBy('name');

                if ($companyId) {
                    $query->whereKey($companyId);
                }

                return $query->with('merchant:id,name,slug,billing_status')
                    ->get()
                    ->map(fn (MerchantCompany $company): array => $this->serializeCompanyOption($company))
                    ->all();
            })
            ->values()
            ->all();
    }

    private function serializeCompanyOption(MerchantCompany $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'access_code' => $company->access_code,
            'document' => $company->document,
            'platform' => $company->platform,
            'status' => $company->status,
            'merchant' => $company->merchant ? $this->serializeMerchant($company->merchant) : null,
        ];
    }

    private function safeActiveMerchant(Request $request): ?Merchant
    {
        try {
            return app(ActiveTenant::class)->merchant($request);
        } catch (\Throwable) {
            return null;
        }
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => $user->cpf,
            'role' => $user->role,
            'status' => $user->status ?? 'active',
        ];
    }

    private function serializeMerchant(Merchant $merchant): array
    {
        return [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'slug' => $merchant->slug,
            'billing_status' => $merchant->billing_status,
        ];
    }

    private function serializeCompany(MerchantCompany $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'access_code' => $company->access_code,
            'document' => $company->document,
            'platform' => $company->platform,
            'status' => $company->status,
        ];
    }
}
