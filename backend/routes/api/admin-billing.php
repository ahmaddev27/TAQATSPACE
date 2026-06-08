<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super-Admin Billing — subscriptions & invoices tracking (Phase-4)
|--------------------------------------------------------------------------
| Read-only subscription tracking plus invoice tracking with manual
| mark-paid / mark-unpaid and payment-receipt upload. No payment gateway.
*/

Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_billing'])
    ->prefix('admin')
    ->group(function (): void {
        // Subscriptions — read-only tracking.
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);

        // Invoices — tracking + manual payment recording.
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::put('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
        Route::put('/invoices/{invoice}/mark-unpaid', [InvoiceController::class, 'markUnpaid']);
        Route::post('/invoices/{invoice}/receipt', [InvoiceController::class, 'uploadReceipt']);
    });
