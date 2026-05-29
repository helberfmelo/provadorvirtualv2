<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopperProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'measurements' => 'array',
            'preferences' => 'array',
            'quality_score' => 'integer',
            'outlier_score' => 'decimal:2',
            'consent_given_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function learningEvents()
    {
        return $this->hasMany(RecommendationLearningEvent::class);
    }

    public function recommendationSessions()
    {
        return $this->hasMany(RecommendationSession::class);
    }
}
