<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'used_virtual_try_on' => 'boolean',
            'recommendation_confidence' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function order()
    {
        return $this->belongsTo(MerchantOrder::class, 'merchant_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function recommendationLog()
    {
        return $this->belongsTo(RecommendationLog::class);
    }

    public function measurementTable()
    {
        return $this->belongsTo(MeasurementTable::class);
    }
}
