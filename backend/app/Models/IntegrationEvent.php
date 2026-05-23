<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'payload' => 'array',
            'occurred_at' => 'datetime',
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

    public function platformConnection()
    {
        return $this->belongsTo(PlatformConnection::class);
    }
}
