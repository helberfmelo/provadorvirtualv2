<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceBody(
            'aguardando_pagamento',
            "Olá {{nome}},\n\nO pagamento da {{empresa}} ainda está pendente. Você pode finalizar pelo Pix aqui: {{link_pix}}\n\nSe preferir cartão, acesse: {{link_checkout}}",
            "Olá {{nome}},\n\nO pagamento da {{empresa}} ainda está pendente. Você pode finalizar pelo Pix com 5% de desconto aqui: {{link_pix}}\n\nSe preferir cartão em até 10x sem juros, acesse: {{link_checkout}}",
        );

        $this->replaceBody(
            'erro_pagamento',
            "Olá {{nome}},\n\nNão conseguimos confirmar o pagamento da {{empresa}}. Tente novamente por Pix ou outro cartão: {{link_checkout}}",
            "Olá {{nome}},\n\nNão conseguimos confirmar o pagamento da {{empresa}}. Tente novamente por Pix com 5% de desconto ou cartão em até 10x sem juros: {{link_checkout}}",
        );

        $this->replaceBody(
            'renovacao_plano',
            "Olá {{nome}},\n\nO plano da {{empresa}} precisa de renovação. Use o link: {{link_renovacao}}\n\nCódigo da empresa: {{codigo_empresa}}",
            "Olá {{nome}},\n\nO plano da {{empresa}} precisa de renovação. Você pode pagar em até 10x sem juros no cartão ou Pix à vista com 5% de desconto: {{link_renovacao}}\n\nCódigo da empresa: {{codigo_empresa}}",
        );
    }

    public function down(): void
    {
        $this->replaceBody(
            'aguardando_pagamento',
            "Olá {{nome}},\n\nO pagamento da {{empresa}} ainda está pendente. Você pode finalizar pelo Pix com 5% de desconto aqui: {{link_pix}}\n\nSe preferir cartão em até 10x sem juros, acesse: {{link_checkout}}",
            "Olá {{nome}},\n\nO pagamento da {{empresa}} ainda está pendente. Você pode finalizar pelo Pix aqui: {{link_pix}}\n\nSe preferir cartão, acesse: {{link_checkout}}",
        );

        $this->replaceBody(
            'erro_pagamento',
            "Olá {{nome}},\n\nNão conseguimos confirmar o pagamento da {{empresa}}. Tente novamente por Pix com 5% de desconto ou cartão em até 10x sem juros: {{link_checkout}}",
            "Olá {{nome}},\n\nNão conseguimos confirmar o pagamento da {{empresa}}. Tente novamente por Pix ou outro cartão: {{link_checkout}}",
        );

        $this->replaceBody(
            'renovacao_plano',
            "Olá {{nome}},\n\nO plano da {{empresa}} precisa de renovação. Você pode pagar em até 10x sem juros no cartão ou Pix à vista com 5% de desconto: {{link_renovacao}}\n\nCódigo da empresa: {{codigo_empresa}}",
            "Olá {{nome}},\n\nO plano da {{empresa}} precisa de renovação. Use o link: {{link_renovacao}}\n\nCódigo da empresa: {{codigo_empresa}}",
        );
    }

    private function replaceBody(string $code, string $from, string $to): void
    {
        DB::table('transactional_emails')
            ->where('code', $code)
            ->where('body', $from)
            ->update(['body' => $to]);
    }
};
