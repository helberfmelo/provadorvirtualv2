<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxonomyMappingSuggestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'reasons' => 'array',
            'impact' => 'array',
            'context' => 'array',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
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

    public function version()
    {
        return $this->belongsTo(TaxonomyVersion::class, 'taxonomy_version_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function merchantCategory()
    {
        return $this->belongsTo(MerchantCategory::class);
    }

    public function merchantBrand()
    {
        return $this->belongsTo(MerchantBrand::class);
    }

    public function taxonomyCategory()
    {
        return $this->belongsTo(TaxonomyCategory::class);
    }

    public function normalizedBrand()
    {
        return $this->belongsTo(NormalizedBrand::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function learningEvents()
    {
        return $this->hasMany(TaxonomyLearningEvent::class);
    }
}
