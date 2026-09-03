<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitClearanceItem extends Model
{
    protected $fillable = [
        'unit_clearance_id',
        'category',
        'room',
        'description',
        'cost',
        'responsible_party',
    ];

    public function clearance()
    {
        return $this->belongsTo(UnitClearance::class, 'unit_clearance_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'unit_clearance_item_id');
    }
}
