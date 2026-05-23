<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Integrations\BigShopActivationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BigShopActivationController extends Controller
{
    public function __construct(private readonly BigShopActivationService $activation) {}

    public function __invoke(Request $request)
    {
        $secret = config('services.bigshop.activation_secret');

        if (! $secret) {
            return response()->json([
                'message' => 'Ativacao BigShop ainda nao configurada.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $this->validSignature($request, $secret)) {
            return response()->json([
                'message' => 'Assinatura BigShop invalida.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->validate([
            'store_id' => ['required', 'string', 'max:120'],
            'store_name' => ['required', 'string', 'max:180'],
            'store_domain' => ['nullable', 'string', 'max:180'],
            'store_url' => ['nullable', 'url', 'max:255'],
            'merchant.email' => ['nullable', 'email', 'max:180'],
            'merchant.name' => ['nullable', 'string', 'max:180'],
            'merchant_email' => ['nullable', 'email', 'max:180'],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:4000'],
            'webhook_secret' => ['nullable', 'string', 'max:4000'],
        ]);

        try {
            return response()->json([
                'data' => $this->activation->activate($data),
            ]);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'bigshop' => $exception->getMessage(),
            ]);
        }
    }

    private function validSignature(Request $request, string $secret): bool
    {
        $timestamp = $request->header('X-BigShop-Timestamp');
        $signature = (string) $request->header('X-BigShop-Signature');

        if (! $timestamp || ! $signature || abs(now()->timestamp - (int) $timestamp) > 600) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        $received = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals($expected, $received);
    }
}
