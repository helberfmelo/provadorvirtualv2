<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionalEmailSend extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function template()
    {
        return $this->belongsTo(TransactionalEmail::class, 'transactional_email_id');
    }

    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function company()
    {
        return $this->belongsTo(MerchantCompany::class, 'merchant_company_id');
    }
}
