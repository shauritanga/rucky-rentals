<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = ['number', 'date', 'description', 'status'];

    public function lines() { return $this->hasMany(JournalLine::class); }
}
