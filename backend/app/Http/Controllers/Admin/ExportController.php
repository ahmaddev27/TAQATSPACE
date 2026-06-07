<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exports,
    ) {}

    /**
     * GET /api/admin/exports/{type} — stream a filtered CSV of the full set.
     *
     * {type} is whitelisted (invoices|subscriptions|users|workspaces); anything
     * else is a 404. The same filters the matching list endpoint accepts are
     * forwarded to the export.
     */
    public function download(Request $request, string $type): StreamedResponse
    {
        if (! in_array($type, ExportService::TYPES, true)) {
            throw new NotFoundHttpException("Unknown export type [{$type}].");
        }

        $filters = $request->only(['status', 'search', 'role', 'workspace_id', 'date_from', 'date_to']);

        return $this->exports->stream($type, $filters);
    }
}
