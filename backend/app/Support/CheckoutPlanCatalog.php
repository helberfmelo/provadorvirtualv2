<?php

namespace App\Support;

class CheckoutPlanCatalog
{
    public const PLAN_ANNUAL = 'annual';

    public const PLAN_MONTHLY = 'monthly';

    public static function plans(): array
    {
        return [
            self::PLAN_ANNUAL => [
                'code' => self::PLAN_ANNUAL,
                'name' => 'Provador Virtual Anual',
                'billing_cycle' => self::PLAN_ANNUAL,
                'interval_months' => 12,
                'price_cents' => 44980,
                'currency' => 'BRL',
                'description' => 'Plano anual com provador, tabela de medidas, recomendação inteligente, integrações padrão e suporte de ativação.',
            ],
            self::PLAN_MONTHLY => [
                'code' => self::PLAN_MONTHLY,
                'name' => 'Provador Virtual Mensal',
                'billing_cycle' => self::PLAN_MONTHLY,
                'interval_months' => 1,
                'price_cents' => 48980,
                'currency' => 'BRL',
                'description' => 'Plano mensal com provador, tabela de medidas, recomendação inteligente, integrações padrão e suporte de ativação.',
            ],
        ];
    }

    public static function pricingConfig(): array
    {
        return [
            'default' => self::pricingVariant('Qualquer plataforma', 48980, 44980),
            'bigshop' => self::pricingVariant('Cliente BigShop', 38980, 34990),
        ];
    }

    public static function pricingFor(array $data): array
    {
        $planCode = (string) ($data['plan_code'] ?? self::PLAN_ANNUAL);
        $platform = ($data['platform'] ?? null) === 'bigshop' ? 'bigshop' : 'default';
        $variant = self::pricingConfig()[$platform];
        $cycle = $variant[$planCode] ?? $variant[self::PLAN_ANNUAL];
        $paymentMethod = match ($data['payment_method'] ?? null) {
            'credit_card' => 'credit_card',
            'boleto' => 'boleto',
            default => 'pix',
        };
        $payableCents = $paymentMethod === 'pix' ? $cycle['pix_total_cents'] : $cycle['card_total_cents'];

        return [
            ...$cycle,
            'label' => $variant['label'],
            'platform_price_key' => $platform,
            'payment_method' => $paymentMethod,
            'billing_cycle' => $planCode,
            'payable_cents' => $payableCents,
            'pix_discount_percent' => $variant['pix_discount_percent'],
            'max_installments' => $cycle['max_installments'],
        ];
    }

    private static function pricingVariant(string $label, int $monthlyCents, int $annualMonthlyCents): array
    {
        $monthlyAnnualizedCents = $monthlyCents * 12;
        $annualCardCents = $annualMonthlyCents * 12;
        $annualSavingsCents = $monthlyAnnualizedCents - $annualCardCents;
        $annualSavingsPercent = $monthlyAnnualizedCents > 0
            ? round(($annualSavingsCents / $monthlyAnnualizedCents) * 100, 1)
            : 0.0;

        return [
            'label' => $label,
            'monthly' => [
                'monthly_cents' => $monthlyCents,
                'card_total_cents' => $monthlyCents,
                'pix_total_cents' => $monthlyCents,
                'period_total_cents' => $monthlyCents,
                'monthly_equivalent_cents' => $monthlyCents,
                'annualized_monthly_total_cents' => $monthlyAnnualizedCents,
                'savings_cents' => 0,
                'savings_percent' => 0.0,
                'max_installments' => 1,
            ],
            'annual' => [
                'monthly_cents' => $annualMonthlyCents,
                'card_total_cents' => $annualCardCents,
                'pix_total_cents' => (int) round($annualCardCents * 0.95),
                'period_total_cents' => $annualCardCents,
                'monthly_equivalent_cents' => $annualMonthlyCents,
                'annualized_monthly_total_cents' => $monthlyAnnualizedCents,
                'savings_cents' => $annualSavingsCents,
                'savings_percent' => $annualSavingsPercent,
                'max_installments' => 10,
            ],
            'pix_discount_percent' => 5,
        ];
    }
}
