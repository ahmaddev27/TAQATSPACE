<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Owner\AuditLogController as OwnerAuditLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Audit log (read-only view of an append-only store)
|--------------------------------------------------------------------------
| Owners see their own workspace's log; platform admins holding
| `manage_admins` see every workspace. No update/delete endpoints exist.
*/
Route::middleware(['auth:sanctum', 'role.owner'])
    ->get('workspace/audit-logs', [OwnerAuditLogController::class, 'index']);

Route::middleware(['auth:sanctum', 'role.admin', 'can.permission:manage_admins'])
    ->get('admin/audit-logs', [AdminAuditLogController::class, 'index']);
