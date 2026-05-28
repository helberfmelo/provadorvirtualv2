<?php

namespace App\Services\Integrations;

use App\Models\PlatformConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BigShopClient
{
    public function probe(PlatformConnection $connection): array
    {
        $response = $this->request($connection, '/v3/getEndPoints');

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'endpoints' => $this->payload($response),
        ];
    }

    public function products(PlatformConnection $connection): array
    {
        $response = $this->request($connection, '/v3/products');

        if (! $response->successful()) {
            throw new RuntimeException('BigShop retornou HTTP '.$response->status().' ao sincronizar produtos.');
        }

        $payload = $this->payload($response);

        return $this->productsFromPayload($payload);
    }

    private function request(PlatformConnection $connection, string $path): Response
    {
        $token = $this->token($connection);
        $storeId = $connection->external_store_id;
        $baseUrl = rtrim($connection->api_base_url ?: 'https://api.bigshop.com.br', '/');

        if (! $storeId) {
            throw new RuntimeException('Informe o store_id da BigShop.');
        }

        if (! $token) {
            throw new RuntimeException('Informe o token x-api da BigShop.');
        }

        return Http::acceptJson()
            ->timeout(20)
            ->withHeaders([
                'x-api' => $token,
                'Store-Id' => $storeId,
            ])
            ->get($baseUrl.$path, [
                'Store-Id' => $storeId,
            ]);
    }

    private function token(PlatformConnection $connection): ?string
    {
        if (! $connection->access_token_encrypted) {
            return null;
        }

        return Crypt::decryptString($connection->access_token_encrypted);
    }

    private function payload(Response $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function productsFromPayload(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        if (isset($payload['products']) && is_array($payload['products'])) {
            return $payload['products'];
        }

        if (! array_is_list($payload)) {
            return [];
        }

        $products = [];
        $hasPaginatorEnvelope = false;

        foreach ($payload as $entry) {
            if (! is_array($entry) || ! $this->isPaginatorEnvelope($entry)) {
                continue;
            }

            $hasPaginatorEnvelope = true;
            foreach ($entry['data'] as $product) {
                $products[] = $product;
            }
        }

        return $hasPaginatorEnvelope ? $products : $payload;
    }

    private function isPaginatorEnvelope(array $entry): bool
    {
        return isset($entry['data'])
            && is_array($entry['data'])
            && (
                array_key_exists('current_page', $entry)
                || array_key_exists('per_page', $entry)
                || array_key_exists('last_page', $entry)
                || array_key_exists('total', $entry)
            );
    }
}
