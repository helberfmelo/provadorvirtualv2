<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'errors' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
}
