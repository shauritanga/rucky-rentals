<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    protected $fillable = ['property_id', 'entry_number', 'entry_date', 'description', 'reference', 'status', 'source_type', 'source_id'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
