<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingSubscription;
use App\Services\CheckoutPaymentManager;
use App\Support\ActiveTenant;
use Illuminate\Http\Request;
use RuntimeException;

class BillingSubscriptionController extends Controller
{
    public function __construct(private readonly CheckoutPaymentManager $checkoutPayments) {}

    public function show(Request $request): array
    {
        return [
            'data' => $this->serialize($this->currentSubscription($request)),
        ];
    }

    public function updateAutoRenewal(Request $request)
    {
        $data = $request->validate([
            'auto_renewal_enabled' => ['required', 'boolean'],
        ]);

        if ((bool) $data['auto_renewal_enabled']) {
            return response()->json([
                'message' => 'A reativação automática da renovação ainda não está disponível pelo painel.',
            ], 422);
        }

        $subscription = $this->currentSubscription($request);
        if (! $subscription) {
            return response()->json([
                'message' => 'Nenhuma assinatura recorrente encontrada para esta empresa.',
            ], 404);
        }

        try {
            $subscription = $this->checkoutPayments->cancelSubscription($subscription);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return [
            'data' => $this->serialize($subscription),
        ];
    }

    private function currentSubscription(Request $request): ?BillingSubscription
    {
        $tenant = app(ActiveTenant::class);
        $merchant = $tenant->merchant($request);
        $company = $tenant->company($request, $merchant);

        return BillingSubscription::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, fn ($query) => $query->where('merchant_company_id', $company->id))
            ->latest('id')
            ->first();
    }

    private function serialize(?BillingSubscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'plan_code' => $subscription->plan_code,
            'billing_cycle' => $subscription->billing_cycle,
            'payment_method' => $subscription->payment_method,
            'status' => $subscription->status,
            'auto_renewal_enabled' => $subscription->auto_renewal_enabled,
            'amount_cents' => $subscription->amount_cents,
            'currency' => $subscription->currency,
            'next_charge_at' => $subscription->next_charge_at?->toISOString(),
            'started_at' => $subscription->started_at?->toISOString(),
            'cancel_requested_at' => $subscription->cancel_requested_at?->toISOString(),
            'cancelled_at' => $subscription->cancelled_at?->toISOString(),
        ];
    }
}
