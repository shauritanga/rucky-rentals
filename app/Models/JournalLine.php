<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_code', 'account_name', 'debit', 'credit'];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_code', 'code');
    }
}
