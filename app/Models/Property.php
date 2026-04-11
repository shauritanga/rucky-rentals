<?php

namespace App\Models;

use App\Support\FloorConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Property extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'country',
        'status',
        'unit_count',
        'occupied_units',
        'total_floors',  // deprecated — kept for backward compat; use floor_config
        'floor_config',
        'manager_user_id',
        'phone',
        'bank_name',
        'bank_account',
        'bank_account_name',
        'swift_code',
    ];

    protected $casts = [
        'floor_config' => 'array',
    ];

    /**
     * Return the ordered floor list for this property.
     * Each element: ['id' => string, 'label' => string, 'sort_order' => int]
     */
    public function floorList(): array
    {
        return FloorConfig::floors(FloorConfig::parse($this->floor_config));
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function leases(): HasManyThrough
    {
        return $this->hasManyThrough(Lease::class, Unit::class);
    }
}
