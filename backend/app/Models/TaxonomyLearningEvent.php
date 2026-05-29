<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxonomyLearningEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'context' => 'array',
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

    public function suggestion()
    {
        return $this->belongsTo(TaxonomyMappingSuggestion::class, 'taxonomy_mapping_suggestion_id');
    }
}
