<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        Request $request,
        ?Merchant $merchant,
        string $event,
        string $category = 'general',
        string $severity = 'info',
        array $metadata = [],
        ?Model $auditable = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'merchant_id' => $merchant?->id,
            'user_id' => $request->user()?->id,
            'event' => $event,
            'category' => $category,
            'severity' => $severity,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn ($value, string $key): bool => str_contains($key, 'token') || str_contains($key, 'secret') || str_contains($key, 'password'))
            ->all();
    }

    private function hashValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash('sha256', $value.config('app.key'));
    }
}
