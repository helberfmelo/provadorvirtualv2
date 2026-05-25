<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class MerchantCompany extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(function (MerchantCompany $company): void {
            $company->ensureAccessCode();
        });
    }

    public static function makeAccessCode(int $id, ?int $year = null): string
    {
        return (string) ($year ?: now()->year).str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public function ensureAccessCode(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'access_code')) {
            return;
        }

        if ($this->access_code) {
            return;
        }

        $this->forceFill([
            'access_code' => self::makeAccessCode((int) $this->getKey()),
        ])->saveQuietly();
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function billingSubscriptions()
    {
        return $this->hasMany(BillingSubscription::class);
    }
}
