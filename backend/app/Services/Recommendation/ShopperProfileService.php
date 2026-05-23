<?php

namespace App\Services\Recommendation;

use App\Models\Product;
use App\Models\ShopperProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ShopperProfileService
{
    private const CONSENT_VERSION = 'pv-consent-2026-05-23';

    private const MEASURE_KEYS = [
        'bust',
        'waist',
        'hip',
        'height',
        'weight',
        'length',
        'shoulder',
    ];

    public function resolve(Product $product, array $measurements, array $profilePayload = []): array
    {
        $consent = (bool) ($profilePayload['consent_measurements']
            ?? $profilePayload['consent']
            ?? $profilePayload['save_profile']
            ?? false);

        $normalizedMeasurements = $this->normalizeMeasurements($measurements);
        $preferences = $this->preferences($profilePayload);

        if (! $consent) {
            return [
                'profile' => null,
                'plain_token' => null,
                'known_profile' => false,
                'consent_given' => false,
                'snapshot' => [
                    'measurements' => $normalizedMeasurements,
                    'preferences' => $preferences,
                    'quality_score' => $this->qualityScore($normalizedMeasurements, $preferences),
                    'consent_version' => null,
                ],
                'response' => [
                    'consent' => false,
                    'known_profile' => false,
                    'message' => 'Medidas usadas somente para esta recomendacao.',
                ],
            ];
        }

        $plainToken = null;
        $knownProfile = false;
        $profile = $this->profileFromPayload($product, $profilePayload);

        if (! $profile) {
            $plainToken = Str::random(48);
            $profile = ShopperProfile::query()->create([
                'uuid' => (string) Str::uuid(),
                'merchant_id' => $product->merchant_id,
                'merchant_company_id' => $product->merchant_company_id,
                'profile_type' => 'anonymous',
                'status' => 'active',
                'write_token_hash' => $this->tokenHash($plainToken),
                'consent_version' => self::CONSENT_VERSION,
                'consent_given_at' => now(),
                'measurements' => $normalizedMeasurements,
                'preferences' => $preferences,
                'quality_score' => $this->qualityScore($normalizedMeasurements, $preferences),
                'last_seen_at' => now(),
                'expires_at' => now()->addDays(180),
            ]);
        } else {
            $knownProfile = true;
            $profile->update([
                'profile_type' => 'known',
                'status' => 'active',
                'consent_version' => self::CONSENT_VERSION,
                'consent_given_at' => $profile->consent_given_at ?? now(),
                'measurements' => array_merge($profile->measurements ?? [], $normalizedMeasurements),
                'preferences' => array_merge($profile->preferences ?? [], $preferences),
                'quality_score' => $this->qualityScore(
                    array_merge($profile->measurements ?? [], $normalizedMeasurements),
                    array_merge($profile->preferences ?? [], $preferences),
                ),
                'last_seen_at' => now(),
                'expires_at' => now()->addDays(180),
            ]);
        }

        $profile->refresh();

        return [
            'profile' => $profile,
            'plain_token' => $plainToken,
            'known_profile' => $knownProfile,
            'consent_given' => true,
            'snapshot' => [
                'profile_uuid' => $profile->uuid,
                'measurements' => $profile->measurements ?? [],
                'preferences' => $profile->preferences ?? [],
                'quality_score' => $profile->quality_score,
                'consent_version' => self::CONSENT_VERSION,
            ],
            'response' => [
                'id' => $profile->uuid,
                'token' => $plainToken,
                'consent' => true,
                'known_profile' => $knownProfile,
                'quality_score' => $profile->quality_score,
                'outlier_score' => (float) $profile->outlier_score,
                'updated_at' => $profile->updated_at?->toISOString(),
                'measurements' => $profile->measurements ?? [],
                'preferences' => $profile->preferences ?? [],
                'message' => $knownProfile
                    ? 'Recomendacao baseada em medidas fornecidas anteriormente.'
                    : 'Perfil salvo para deixar as proximas recomendacoes mais rapidas.',
            ],
        ];
    }

    public function forget(array $profilePayload): bool
    {
        $profile = $this->profileFromUuidAndToken(
            (string) ($profilePayload['profile_id'] ?? ''),
            (string) ($profilePayload['profile_token'] ?? ''),
        );

        if (! $profile) {
            return false;
        }

        $profile->update([
            'status' => 'forgotten',
            'write_token_hash' => null,
            'measurements' => null,
            'preferences' => null,
            'outlier_score' => 0,
            'expires_at' => now(),
        ]);

        return true;
    }

    private function profileFromPayload(Product $product, array $profilePayload): ?ShopperProfile
    {
        $profile = $this->profileFromUuidAndToken(
            (string) ($profilePayload['profile_id'] ?? ''),
            (string) ($profilePayload['profile_token'] ?? ''),
        );

        if (! $profile) {
            return null;
        }

        if ((int) $profile->merchant_id !== (int) $product->merchant_id) {
            return null;
        }

        if ($profile->merchant_company_id && (int) $profile->merchant_company_id !== (int) $product->merchant_company_id) {
            return null;
        }

        return $profile;
    }

    private function profileFromUuidAndToken(string $uuid, string $token): ?ShopperProfile
    {
        if ($uuid === '' || $token === '') {
            return null;
        }

        $profile = ShopperProfile::query()
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->first();

        if (! $profile || ! hash_equals((string) $profile->write_token_hash, $this->tokenHash($token))) {
            return null;
        }

        return $profile;
    }

    private function normalizeMeasurements(array $measurements): array
    {
        $normalized = [];

        foreach (self::MEASURE_KEYS as $key) {
            $value = Arr::get($measurements, $key);

            if ($value === null || $value === '') {
                continue;
            }

            $number = round((float) $value, 2);

            if ($number > 0) {
                $normalized[$key] = $number;
            }
        }

        return $normalized;
    }

    private function preferences(array $profilePayload): array
    {
        return collect([
            'gender' => $profilePayload['gender'] ?? null,
            'body_shape' => $profilePayload['body_shape'] ?? null,
            'fit_preference' => $profilePayload['fit_preference'] ?? null,
        ])
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function qualityScore(array $measurements, array $preferences): int
    {
        $coreMeasures = collect(['bust', 'waist', 'hip', 'height', 'weight'])
            ->filter(fn (string $key) => array_key_exists($key, $measurements))
            ->count();

        $score = 20 + ($coreMeasures * 12) + (count($preferences) * 6);

        if ($coreMeasures >= 4) {
            $score += 12;
        }

        return max(0, min(100, $score));
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
