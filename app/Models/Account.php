<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type', 'category', 'balance', 'ytd_activity', 'description'];

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class, 'account_code', 'code');
    }
}
