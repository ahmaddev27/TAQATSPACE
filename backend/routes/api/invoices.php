<?php

declare(strict_types=1);

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Member\InvoiceController as MemberInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Invoicing (M07 — T041, T042, T044)
|--------------------------------------------------------------------------
| Owner invoice management, member invoice history, and PDF download.
| All routes require an authenticated Sanctum token.
*/

// Owner — invoice management, scoped to the owner's workspace.
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/invoices')
    ->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy']);
        Route::put('/{invoice}/pay', [InvoiceController::class, 'pay']);
        Route::post('/{invoice}/receipt', [InvoiceController::class, 'uploadReceipt']);
        Route::put('/{invoice}/approve-receipt', [InvoiceController::class, 'approveReceipt']);
        Route::put('/{invoice}/reject-receipt', [InvoiceController::class, 'rejectReceipt']);
        Route::put('/{invoice}/partial-payment', [InvoiceController::class, 'partialPayment']);
        Route::post('/{invoice}/payment', [InvoiceController::class, 'recordPayment']);
        Route::post('/{invoice}/remind', [InvoiceController::class, 'remind']);
    });

// Freelancer — read-only invoice history.
Route::middleware(['auth:sanctum', 'role.freelancer'])
    ->prefix('member/invoices')
    ->group(function (): void {
        Route::get('/', [MemberInvoiceController::class, 'index']);
        Route::get('/{invoice}', [MemberInvoiceController::class, 'show']);
        Route::post('/{invoice}/receipt', [MemberInvoiceController::class, 'uploadReceipt']);
    });

// PDF download — owner OR the member the invoice belongs to (checked in controller).
Route::middleware('auth:sanctum')
    ->get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
