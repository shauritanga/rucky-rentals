<?php
require 'vendor/autoload.php';
$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\JournalLine;
use App\Models\AccountingEvent;

$invoice = Invoice::latest()->first();
if ($invoice) {
    echo "Invoice #" . $invoice->id . ":\n";
    echo "  Total: " . $invoice->total . "\n";
    echo "  Currency: " . $invoice->currency . "\n";
    echo "  Exchange Rate: " . $invoice->exchange_rate . "\n";
    echo "  Total in Base: " . $invoice->total_in_base . "\n";
    echo "  Status: " . $invoice->status . "\n";
}

$payment = Payment::latest()->first();
if ($payment) {
    echo "\nPayment #" . $payment->id . ":\n";
    echo "  Amount: " . $payment->amount . "\n";
    echo "  Currency: " . $payment->currency . "\n";
    echo "  Exchange Rate: " . $payment->exchange_rate . "\n";
    echo "  Amount in Base: " . $payment->amount_in_base . "\n";
    echo "  Status: " . $payment->status . "\n";
}

echo "\nJournal Lines (Cash Account 1000):\n";
$cashLines = JournalLine::where('account_code', '1000')->get();
foreach ($cashLines as $line) {
    echo "  Dr=" . $line->debit . ", Cr=" . $line->credit . ", Ref=" . $line->reference . "\n";
}

echo "\nAccounting Events (last 5):\n";
$events = AccountingEvent::latest()->limit(5)->get();
foreach ($events as $e) {
    echo "  " . $e->event_type . " (" . $e->status . ") - " . $e->entity_type . "#" . $e->entity_id . "\n";
    if ($e->status === 'failed') {
        echo "    Error: " . $e->error_message . "\n";
    }
}
