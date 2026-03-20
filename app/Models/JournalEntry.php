<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = ['entry_number', 'entry_date', 'description', 'reference', 'status'];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
