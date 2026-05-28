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
        return $this->paginatedItems($connection, '/v3/products', 'produtos');
    }

    public function productGrids(PlatformConnection $connection): array
    {
        return $this->paginatedItems($connection, '/v3/product_grids', 'grades');
    }

    private function request(PlatformConnection $connection, string $path, array $query = []): Response
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
            ->get($baseUrl.$path, array_merge([
                'Store-Id' => $storeId,
            ], $query));
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

    private function paginatedItems(PlatformConnection $connection, string $path, string $label): array
    {
        $items = [];
        $page = 1;
        $visitedPages = [];
        $maxPages = 50;

        while ($page <= $maxPages && ! in_array($page, $visitedPages, true)) {
            $visitedPages[] = $page;
            $response = $this->request($connection, $path, [
                'page' => $page,
                'per_page' => 100,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('BigShop retornou HTTP '.$response->status().' ao sincronizar '.$label.'.');
            }

            $payload = $this->payload($response);
            array_push($items, ...$this->itemsFromPayload($payload));

            $nextPage = $this->nextPage($payload, $page);
            if (! $nextPage) {
                break;
            }

            $page = $nextPage;
        }

        return $items;
    }

    private function itemsFromPayload(array $payload): array
    {
        foreach (['data', 'products', 'product_grids', 'grids'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : array_values($payload[$key]);
            }
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

    private function nextPage(array $payload, int $currentPage): ?int
    {
        $envelope = $payload;

        if (array_is_list($payload) && isset($payload[0]) && is_array($payload[0]) && $this->isPaginatorEnvelope($payload[0])) {
            $envelope = $payload[0];
        }

        $reportedCurrentPage = $this->integer($envelope['current_page'] ?? $currentPage) ?? $currentPage;
        $lastPage = $this->integer($envelope['last_page'] ?? null);

        if ($lastPage && $reportedCurrentPage < $lastPage) {
            return $reportedCurrentPage + 1;
        }

        if (! empty($envelope['next_page_url']) && $this->itemsFromPayload($envelope) !== []) {
            return $reportedCurrentPage + 1;
        }

        return null;
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

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) preg_replace('/[^0-9-]/', '', (string) $value);
    }
}
