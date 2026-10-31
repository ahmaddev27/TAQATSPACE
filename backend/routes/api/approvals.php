<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ApprovalController as AdminApprovalController;
use App\Http\Controllers\Owner\ApprovalController as OwnerApprovalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Approvals framework
|--------------------------------------------------------------------------
| Owners list and withdraw their own workspace's change requests; platform
| admins holding `approve_requests` see the whole queue and decide it.
| Submitting is done server-side by the feature that needs approval.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->prefix('workspace/approvals')
    ->group(function (): void {
        Route::get('/', [OwnerApprovalController::class, 'index']);
        Route::post('/{approvalRequest}/cancel', [OwnerApprovalController::class, 'cancel']);
    });

Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:approve_requests'])
    ->prefix('admin/approvals')
    ->group(function (): void {
        Route::get('/', [AdminApprovalController::class, 'index']);
        Route::post('/{approvalRequest}/approve', [AdminApprovalController::class, 'approve']);
        Route::post('/{approvalRequest}/reject', [AdminApprovalController::class, 'reject']);
    });
