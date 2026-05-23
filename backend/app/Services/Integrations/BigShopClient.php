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

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        if (isset($payload['products']) && is_array($payload['products'])) {
            return $payload['products'];
        }

        return array_is_list($payload) ? $payload : [];
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
                'store-id' => $storeId,
            ])
            ->get($baseUrl.$path, [
                'store-id' => $storeId,
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
}
