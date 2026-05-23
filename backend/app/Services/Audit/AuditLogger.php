<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        ?User $actor = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'merchant_id' => $merchant?->id,
            'merchant_company_id' => data_get($metadata, 'merchant_company_id') ?: data_get($metadata, 'company_id'),
            'user_id' => $actor?->id ?? $request->user()?->id,
            'event' => $event,
            'category' => $category,
            'module' => data_get($metadata, 'module') ?: $category,
            'action' => data_get($metadata, 'action'),
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
        return collect($metadata)->mapWithKeys(function ($value, string $key): array {
            return [$key => $this->sanitizeValue($key, $value)];
        })->all();
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[masked]';
        }

        if (is_array($value)) {
            return $this->sanitize($value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower($key);

        return collect(['token', 'secret', 'password', 'authorization', 'api_key', 'x-api'])
            ->contains(fn (string $needle): bool => str_contains($normalized, $needle));
    }

    private function hashValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash('sha256', $value.config('app.key'));
    }
}
