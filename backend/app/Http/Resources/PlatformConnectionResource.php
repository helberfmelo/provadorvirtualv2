<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'platform' => $this->platform,
            'external_store_id' => $this->external_store_id,
            'api_base_url' => $this->api_base_url,
            'feed_url' => $this->feed_url,
            'feed_format' => $this->feed_format ?: 'google_xml',
            'status' => $this->status,
            'has_access_token' => filled($this->access_token_encrypted),
            'has_webhook_secret' => filled($this->webhook_secret_encrypted),
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'last_error' => $this->last_error,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
