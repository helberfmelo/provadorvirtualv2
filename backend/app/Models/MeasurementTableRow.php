<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeasurementTableRow extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'measurements' => 'array',
            'composite_measurements' => 'array',
            'metadata' => 'array',
        ];
    }

    public function measurementTable()
    {
        return $this->belongsTo(MeasurementTable::class);
    }
}
