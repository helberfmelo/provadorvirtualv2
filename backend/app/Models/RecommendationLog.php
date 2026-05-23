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
            'outlier_score' => 'decimal:2',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(RecommendationFeedback::class);
    }

    public function session()
    {
        return $this->belongsTo(RecommendationSession::class, 'recommendation_session_id');
    }

    public function learningEvents()
    {
        return $this->hasMany(RecommendationLearningEvent::class);
    }
}
