<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'auto_renewal_enabled' => 'boolean',
            'next_charge_at' => 'datetime',
            'started_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_provider_sync_at' => 'datetime',
            'provider_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function company()
    {
        return $this->belongsTo(MerchantCompany::class, 'merchant_company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
