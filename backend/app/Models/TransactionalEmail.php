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
                'description' => 'Enviado quando uma empresa e seu primeiro acesso são criados.',
                'subject' => 'Seu acesso ao Provador Virtual',
                'body' => "Olá {{nome}},\n\nSeu cadastro da empresa {{empresa}} foi criado.\nE-mail de acesso: {{email_acesso}}\nCódigo da empresa: {{codigo_empresa}}\nAcesse: {{link_login}}\n\nProvador Virtual",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'pagamento_confirmado',
                'name' => 'Pagamento confirmado',
                'description' => 'Confirma pagamento aprovado e libera o acesso do lojista.',
                'subject' => 'Pagamento aprovado no Provador Virtual',
                'body' => "Olá {{nome}},\n\nRecebemos o pagamento da {{empresa}} e o portal já está liberado.\nCódigo da empresa: {{codigo_empresa}}\nEntrar: {{link_login}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'aguardando_pagamento',
                'name' => 'Aguardando pagamento',
                'description' => 'Reenvia dados de Pix quando o checkout ainda está pendente.',
                'subject' => 'Finalize seu pagamento do Provador Virtual',
                'body' => "Olá {{nome}},\n\nO pagamento da {{empresa}} ainda está pendente. Você pode finalizar pelo Pix com 5% de desconto aqui: {{link_pix}}\n\nSe preferir cartão em até 10x sem juros, acesse: {{link_checkout}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'erro_pagamento',
                'name' => 'Erro no pagamento',
                'description' => 'Enviado quando Pix expira ou cartão é recusado.',
                'subject' => 'Não foi possível confirmar seu pagamento',
                'body' => "Olá {{nome}},\n\nNão conseguimos confirmar o pagamento da {{empresa}}. Tente novamente por Pix com 5% de desconto ou cartão em até 10x sem juros: {{link_checkout}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
            [
                'code' => 'recuperacao_senha',
                'name' => 'Recuperação de senha',
                'description' => 'Enviado no fluxo de redefinição de senha.',
                'subject' => 'Recupere sua senha do Provador Virtual',
                'body' => "Olá {{nome}},\n\nUse este link para criar uma nova senha: {{link_recuperacao}}\n\nSe você não pediu isso, ignore este e-mail.",
                'variables' => ['nome', 'link_recuperacao'],
                'is_active' => true,
            ],
            [
                'code' => 'renovacao_plano',
                'name' => 'Renovação de plano',
                'description' => 'Enviado antes ou depois do vencimento para renovar o plano anual.',
                'subject' => 'Renove o Provador Virtual da sua loja',
                'body' => "Olá {{nome}},\n\nO plano da {{empresa}} precisa de renovação. Você pode pagar em até 10x sem juros no cartão ou Pix à vista com 5% de desconto: {{link_renovacao}}\n\nCódigo da empresa: {{codigo_empresa}}",
                'variables' => $commonVariables,
                'is_active' => true,
            ],
        ];
    }
}
