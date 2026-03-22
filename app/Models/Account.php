<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    protected $fillable = ['property_id', 'code', 'name', 'type', 'category', 'balance', 'ytd_activity', 'description'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class, 'account_code', 'code');
    }
}
