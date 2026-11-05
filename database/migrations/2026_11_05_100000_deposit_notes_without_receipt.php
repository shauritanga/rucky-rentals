<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** A security deposit gets no receipt; correct the note already written on deposit proformas. */
    public function up(): void
    {
        DB::table('invoices')
            ->where('kind', 'deposit')
            ->where('notes', 'Refundable security deposit. Not subject to VAT; a payment receipt is issued on payment.')
            ->update(['notes' => 'Refundable security deposit. Not subject to VAT. No receipt is issued.']);
    }

    public function down(): void
    {
        // Wording only; nothing to restore.
    }
};
