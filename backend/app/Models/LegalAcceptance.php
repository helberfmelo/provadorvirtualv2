<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalAcceptance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function company()
    {
        return $this->belongsTo(MerchantCompany::class, 'merchant_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->morphTo();
    }
}
