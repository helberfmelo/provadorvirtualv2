<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionalEmail extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCode(string $value): string
    {
        return Str::slug($value, '_');
    }

    public static function ensureDefaults(): void
    {
        foreach (self::defaultTemplates() as $template) {
            self::query()->firstOrCreate(
                ['code' => $template['code']],
                $template,
            );
        }
    }

    public static function defaultTemplates(): array
    {
        $commonVariables = ['nome', 'empresa', 'codigo_empresa', 'email_acesso', 'link_login', 'link_checkout', 'link_pix', 'link_renovacao', 'valor'];

        return [
            [
                'code' => 'cadastro_realizado',
                'name' => 'Cadastro realizado',
                'description' => 'Enviado quando uma empresa e seu primeiro acesso sao criados.',
                'subject' => 'Seu acesso ao Provador Virtual',
                'body' => "Ola {{nome}},\n\nSeu cadastro da empresa {{empresa}} foi criado.\nE-mail de acesso: {{email_acesso}}\nCodigo da empresa: {{codigo_empresa}}\nAcesse: {{link_login}}\n\nProvador Virtual",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'pagamento_confirmado',
                'name' => 'Pagamento confirmado',
                'description' => 'Confirma pagamento aprovado e libera o acesso do lojista.',
                'subject' => 'Pagamento aprovado no Provador Virtual',
                'body' => "Ola {{nome}},\n\nRecebemos o pagamento da {{empresa}} e o portal ja esta liberado.\nCodigo da empresa: {{codigo_empresa}}\nEntrar: {{link_login}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'aguardando_pagamento',
                'name' => 'Aguardando pagamento',
                'description' => 'Reenvia dados de Pix quando o checkout ainda esta pendente.',
                'subject' => 'Finalize seu pagamento do Provador Virtual',
                'body' => "Ola {{nome}},\n\nO pagamento da {{empresa}} ainda esta pendente. Voce pode finalizar pelo Pix aqui: {{link_pix}}\n\nSe preferir cartao, acesse: {{link_checkout}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'erro_pagamento',
                'name' => 'Erro no pagamento',
                'description' => 'Enviado quando Pix expira ou cartao e recusado.',
                'subject' => 'Nao foi possivel confirmar seu pagamento',
                'body' => "Ola {{nome}},\n\nNao conseguimos confirmar o pagamento da {{empresa}}. Tente novamente por Pix ou outro cartao: {{link_checkout}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'recuperacao_senha',
                'name' => 'Recuperacao de senha',
                'description' => 'Enviado no fluxo de redefinicao de senha.',
                'subject' => 'Recupere sua senha do Provador Virtual',
                'body' => "Ola {{nome}},\n\nUse este link para criar uma nova senha: {{link_recuperacao}}\n\nSe voce nao pediu isso, ignore este e-mail.",
                'variables' => ['nome', 'link_recuperacao'],
                'is_active' => true,
            ],
            [
                'code' => 'renovacao_plano',
                'name' => 'Renovacao de plano',
                'description' => 'Enviado antes ou depois do vencimento para renovar o plano anual.',
                'subject' => 'Renove o Provador Virtual da sua loja',
                'body' => "Ola {{nome}},\n\nO plano da {{empresa}} precisa de renovacao. Use o link: {{link_renovacao}}\n\nCodigo da empresa: {{codigo_empresa}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
        ];
    }
}
