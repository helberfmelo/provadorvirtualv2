<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecommendationLearningEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'outlier_score' => 'decimal:2',
            'learning_weight' => 'decimal:3',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function recommendationLog()
    {
        return $this->belongsTo(RecommendationLog::class);
    }

    public function shopperProfile()
    {
        return $this->belongsTo(ShopperProfile::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
