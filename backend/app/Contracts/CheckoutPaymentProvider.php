<?php

namespace App\Contracts;

use App\Models\CheckoutSession;
use App\Models\PaymentEvent;

interface CheckoutPaymentProvider
{
    public function key(): string;

    public function label(): string;

    public function configuration(): array;

    public function createOrder(CheckoutSession $session, array $buyerData): CheckoutSession;

    public function handleWebhook(array $payload, array $headers, string $rawBody, array $query = []): PaymentEvent;

    public function syncPendingCheckouts(int $limit = 50): array;

    public function syncCheckoutSession(CheckoutSession $session): CheckoutSession;

    public function publicCheckoutUrl(string $reference): string;
}
