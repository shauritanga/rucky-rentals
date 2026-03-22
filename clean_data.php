<?php
require 'vendor/autoload.php';
$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Lease;
use App\Models\JournalLine;
use App\Models\JournalEntry;
use App\Models\AccountingEvent;

// Delete all old test data
echo "Deleting old data...\n";

// Delete payments and invoices
Payment::truncate();
Invoice::truncate();
Lease::truncate();

// Delete GL entries
JournalEntry::truncate();
JournalLine::truncate();

// Delete accounting events
AccountingEvent::truncate();

echo "Done! All data cleared.\n";
