<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'manager_user_id',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
