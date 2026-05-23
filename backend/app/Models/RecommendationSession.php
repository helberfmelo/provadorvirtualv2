<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecommendationSession extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'shopper_profile' => 'array',
            'profile_snapshot' => 'array',
            'consent_given' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function shopperProfile()
    {
        return $this->belongsTo(ShopperProfile::class);
    }
}
