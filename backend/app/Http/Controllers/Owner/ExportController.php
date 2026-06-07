<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Owner\OwnerExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExportController extends Controller
{
    public function __construct(
        private readonly OwnerExportService $exports,
    ) {}

    /**
     * GET /api/workspace/exports/{type} — stream a filtered CSV scoped to the
     * authenticated owner's OWN workspace.
     *
     * {type} is whitelisted (invoices|members); anything else is a 404. The same
     * filters the matching owner list endpoint accepts are forwarded to the
     * export, so the download matches what the owner sees on screen.
     */
    public function download(Request $request, string $type): StreamedResponse
    {
        if (! in_array($type, OwnerExportService::TYPES, true)) {
            throw new NotFoundHttpException("Unknown export type [{$type}].");
        }

        $workspace = $request->user()->workspace;

        if ($workspace === null) {
            throw new NotFoundHttpException('No workspace for the authenticated owner.');
        }

        $filters = $request->only([
            'status', 'search', 'month', 'sort', 'direction', // invoices + members
            'category', 'from', 'to',                          // expenses
        ]);

        return $this->exports->stream($type, $workspace, $filters);
    }
}
