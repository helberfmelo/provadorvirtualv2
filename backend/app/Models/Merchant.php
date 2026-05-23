<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_owner'])
            ->withTimestamps();
    }

    public function companies()
    {
        return $this->hasMany(MerchantCompany::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function measurementTables()
    {
        return $this->hasMany(MeasurementTable::class);
    }

    public function widgetInstalls()
    {
        return $this->hasMany(WidgetInstall::class);
    }

    public function platformConnections()
    {
        return $this->hasMany(PlatformConnection::class);
    }
}
