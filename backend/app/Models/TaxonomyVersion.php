<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxonomyVersion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function suggestions()
    {
        return $this->hasMany(TaxonomyMappingSuggestion::class);
    }
}
