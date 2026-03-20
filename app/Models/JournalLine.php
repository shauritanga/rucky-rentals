<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_id', 'description', 'debit', 'credit'];

    public function entry() { return $this->belongsTo(JournalEntry::class); }
    public function account() { return $this->belongsTo(Account::class); }
}
