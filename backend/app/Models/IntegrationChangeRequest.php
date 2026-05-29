<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAYMENT_REQUESTED = 'payment_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
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
