<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantReturn extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'used_virtual_try_on' => 'boolean',
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

    public function items()
    {
        return $this->hasMany(MerchantReturnItem::class)->orderBy('id');
    }
}
