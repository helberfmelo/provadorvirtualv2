<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecommendationLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'input_measurements' => 'array',
            'score_breakdown' => 'array',
            'fit_notes' => 'array',
            'warnings' => 'array',
        ];
    }
}
