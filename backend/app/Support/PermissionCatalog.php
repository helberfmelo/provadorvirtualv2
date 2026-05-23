<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\User;

class PermissionCatalog
{
    public static function merchantModules(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Resumo da empresa e indicadores principais.'],
            ['key' => 'products', 'label' => 'Produtos', 'description' => 'Produtos, variacoes e vinculo com tabelas.'],
            ['key' => 'measurement_tables', 'label' => 'Tabelas de medidas', 'description' => 'Cadastro e revisao das tabelas usadas no widget.'],
            ['key' => 'imports', 'label' => 'Importacoes', 'description' => 'CSV, XML e cargas assistidas.'],
            ['key' => 'ai_assistant', 'label' => 'Assistente IA', 'description' => 'Sugestoes de tabela por texto, CSV ou OCR.'],
            ['key' => 'analytics', 'label' => 'Analytics', 'description' => 'Metricas de recomendacao e feedback.'],
            ['key' => 'widget', 'label' => 'Widget', 'description' => 'Snippet, dominios e personalizacao visual.'],
            ['key' => 'integrations', 'label' => 'Integracoes', 'description' => 'Conexoes com plataformas de e-commerce.'],
            ['key' => 'go_live', 'label' => 'Go-live', 'description' => 'Checklist de publicacao assistida.'],
            ['key' => 'users', 'label' => 'Usuarios', 'description' => 'Usuarios, acessos e permissoes da empresa.'],
        ];
    }

    public static function saasModules(): array
    {
        return [
            ['key' => 'saas_dashboard', 'label' => 'Painel SaaS', 'description' => 'Resumo operacional do Provador Virtual.'],
            ['key' => 'saas_companies', 'label' => 'Empresas', 'description' => 'Cadastro e manutencao de empresas clientes.'],
            ['key' => 'saas_users', 'label' => 'Usuarios SaaS', 'description' => 'Usuarios internos, suporte e acessos de lojistas.'],
            ['key' => 'saas_emails', 'label' => 'E-mails', 'description' => 'Credenciais SMTP e e-mails transacionais.'],
            ['key' => 'saas_audit', 'label' => 'Auditoria', 'description' => 'Logs, diagnosticos e rastreabilidade.'],
        ];
    }

    public static function normalize(?array $permissions, string $scope): array
    {
        $permissions ??= [];
        $normalized = [];

        foreach (self::modules($scope) as $module) {
            $key = $module['key'];
            $view = (bool) data_get($permissions, "{$key}.view", false);
            $edit = (bool) data_get($permissions, "{$key}.edit", false);

            if ($edit) {
                $view = true;
            }

            $normalized[$key] = [
                'view' => $view,
                'edit' => $edit,
            ];
        }

        return $normalized;
    }

    public static function full(string $scope): array
    {
        $permissions = [];

        foreach (self::modules($scope) as $module) {
            $permissions[$module['key']] = [
                'view' => true,
                'edit' => true,
            ];
        }

        return $permissions;
    }

    public static function empty(string $scope): array
    {
        return self::normalize([], $scope);
    }

    public static function forMerchantUser(User $user, Merchant $merchant): array
    {
        if (in_array($user->role, ['admin', 'support'], true)) {
            return self::full('merchant');
        }

        $pivot = self::merchantPivot($user, $merchant);

        if (! $pivot) {
            return self::empty('merchant');
        }

        if ((bool) ($pivot->is_owner ?? false) || ($pivot->role ?? null) === 'owner') {
            return self::full('merchant');
        }

        return self::normalize(self::decode($pivot->permissions ?? null), 'merchant');
    }

    public static function forSaasUser(User $user): array
    {
        if (! in_array($user->role, ['admin', 'support'], true)) {
            return self::empty('saas');
        }

        return self::normalize($user->permissions ?: self::full('saas'), 'saas');
    }

    public static function canMerchant(User $user, Merchant $merchant, string $module, string $action = 'view'): bool
    {
        if (in_array($user->role, ['admin', 'support'], true)) {
            return true;
        }

        $pivot = self::merchantPivot($user, $merchant);

        if (! $pivot || ($pivot->status ?? 'active') !== 'active') {
            return false;
        }

        if ((bool) ($pivot->is_owner ?? false) || ($pivot->role ?? null) === 'owner') {
            return true;
        }

        return (bool) data_get(self::forMerchantUser($user, $merchant), "{$module}.{$action}", false);
    }

    public static function canSaas(User $user, string $module, string $action = 'view'): bool
    {
        if (! in_array($user->role, ['admin', 'support'], true)) {
            return false;
        }

        return (bool) data_get(self::forSaasUser($user), "{$module}.{$action}", false);
    }

    public static function decode(mixed $permissions): ?array
    {
        if (is_array($permissions)) {
            return $permissions;
        }

        if (! is_string($permissions) || $permissions === '') {
            return null;
        }

        $decoded = json_decode($permissions, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function modules(string $scope): array
    {
        return $scope === 'saas' ? self::saasModules() : self::merchantModules();
    }

    private static function merchantPivot(User $user, Merchant $merchant): ?object
    {
        return $user->merchants()
            ->whereKey($merchant->id)
            ->first()
            ?->pivot;
    }
}
