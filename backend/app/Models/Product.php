<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
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

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function measurementTable()
    {
        return $this->belongsTo(MeasurementTable::class);
    }
}
