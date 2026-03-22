<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\AccountingService;

class PaymentObserver
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Handle the Payment "created" event.
     * Post entry if payment is created in paid status
     */
    public function created(Payment $payment): void
    {
        $this->accountingService->postPayment($payment);
    }

    /**
     * Handle the Payment "updated" event.
     * Handle status transitions: post if moving to paid, void if moving away from paid
     */
    public function updated(Payment $payment): void
    {
        $originalStatus = $payment->getOriginal('status');

        // If original status is unavailable, skip transition logic safely.
        if ($originalStatus === null) {
            return;
        }

        // If transitioning TO paid, post the entry
        if ($payment->status === 'paid' && $originalStatus !== 'paid') {
            $this->accountingService->postPayment($payment);
            return;
        }

        // If transitioning FROM paid to something else, void the entry
        if ($originalStatus === 'paid' && $payment->status !== 'paid') {
            $this->accountingService->voidPayment($payment);
            return;
        }
    }

    /**
     * Handle the Payment "deleting" event.
     * Void the entry before deletion if it was paid
     */
    public function deleting(Payment $payment): void
    {
        // Only void if it was posted (paid status)
        if ($payment->status === 'paid') {
            $this->accountingService->voidPayment($payment);
        }
    }
}
