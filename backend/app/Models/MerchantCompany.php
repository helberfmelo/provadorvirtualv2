<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
