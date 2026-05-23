<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeasurementTable extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function rows()
    {
        return $this->hasMany(MeasurementTableRow::class)->orderBy('sort_order');
    }
}
