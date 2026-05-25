<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutAcceptance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'accepted_terms' => 'boolean',
            'accepted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class);
    }
}
