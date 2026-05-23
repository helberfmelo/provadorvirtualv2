<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecommendationFeedback extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'recommendation_feedbacks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'was_helpful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function recommendationLog()
    {
        return $this->belongsTo(RecommendationLog::class);
    }
}
