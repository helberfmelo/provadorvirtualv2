<?php

namespace App\Services\Legal;

use App\Models\LegalAcceptance;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LegalAcceptanceRecorder
{
    public function record(
        Request $request,
        string $context,
        string $documentType,
        string $termsVersion,
        ?string $privacyVersion = null,
        ?Merchant $merchant = null,
        ?MerchantCompany $company = null,
        ?User $user = null,
        ?Model $source = null,
        ?\DateTimeInterface $acceptedAt = null,
        array $metadata = [],
    ): LegalAcceptance {
        return LegalAcceptance::query()->create([
            'merchant_id' => $merchant?->id,
            'merchant_company_id' => $company?->id,
            'user_id' => $user?->id,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'context' => $context,
            'document_type' => $documentType,
            'terms_version' => $termsVersion,
            'privacy_version' => $privacyVersion,
            'accepted_at' => $acceptedAt ?? now(),
            'ip_address' => $request->ip(),
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    private function hashValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return hash('sha256', $value.config('app.key'));
    }
}
