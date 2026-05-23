<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\ActiveTenant;
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

        [$merchant, $company] = $this->resolveLoginContext($user, $request);
        $abilities = ['role:'.$user->role];

        if ($merchant) {
            $abilities[] = 'merchant:'.$merchant->id;
        }

        if ($company) {
            $abilities[] = 'company:'.$company->id;
        }

        $token = $user->createToken('provadorvirtual-spa', $abilities)->plainTextToken;

        app(AuditLogger::class)->log($request, $merchant, 'auth.login', 'auth', 'info', [
            'email' => $user->email,
            'company_access' => $company?->access_code,
        ], actor: $user);

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
            'active_merchant' => $merchant ? $this->serializeMerchant($merchant) : null,
            'active_company' => $company ? $this->serializeCompany($company) : null,
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
            'merchants' => $user->merchants()
                ->select('merchants.id', 'merchants.name', 'merchants.slug', 'merchants.billing_status')
                ->get(),
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

            if (! $this->canAccessMerchant($user, $merchant)) {
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

        $merchantIds = $user->merchants()->pluck('merchants.id');
        $companyCount = MerchantCompany::query()->whereIn('merchant_id', $merchantIds)->count();

        if ($companyCount > 1) {
            throw ValidationException::withMessages([
                'company_access' => ['Informe o codigo da loja ou CNPJ para entrar no portal da empresa.'],
            ]);
        }

        $merchant = $user->merchants()->orderBy('merchants.id')->first();
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

        return $user->merchants()->whereKey($merchant->id)->exists();
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
