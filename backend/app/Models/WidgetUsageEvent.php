<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WidgetUsageEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function recommendationLog()
    {
        return $this->belongsTo(RecommendationLog::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function measurementTable()
    {
        return $this->belongsTo(MeasurementTable::class);
    }
}
