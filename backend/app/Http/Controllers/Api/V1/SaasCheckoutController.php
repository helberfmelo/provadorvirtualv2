<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CheckoutPaymentManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaasCheckoutController extends Controller
{
    public function __construct(private readonly CheckoutPaymentManager $checkoutPayments) {}

    public function show(Request $request): array
    {
        $this->ensureAdmin($request);

        return [
            'data' => $this->serialize(),
        ];
    }

    public function update(Request $request): array
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'payment_provider' => [
                'required',
                'string',
                Rule::in([
                    CheckoutPaymentManager::PROVIDER_MERCADO_PAGO,
                    CheckoutPaymentManager::PROVIDER_PAGARME,
                    'mercado-pago',
                    'mercadopago',
                    'pagar.me',
                    'pagarme',
                ]),
            ],
        ]);

        $this->checkoutPayments->setCurrentProvider((string) $data['payment_provider']);

        return [
            'data' => $this->serialize(),
        ];
    }

    private function serialize(): array
    {
        $activeProvider = $this->checkoutPayments->currentProviderKey();

        return [
            'payment_provider' => $activeProvider,
            'active_provider_configured' => $this->checkoutPayments->activeProviderConfigured(),
            'providers' => $this->checkoutPayments->availableProviders(),
            'checkout' => $this->checkoutPayments->configuration(),
        ];
    }

    private function ensureAdmin(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }
}
