<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\ImportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PreviewImportRequest;
use App\Http\Resources\ImportBatchResource;
use App\Repositories\Eloquent\ImportRepository;
use App\Services\Finance\CatalogAccess;
use App\Services\Finance\Import\ImportService;
use App\Support\ApiResponse;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * XLSX imports into the request's workspace. Routes sit behind the
 * `finance_v2` flag and the workspace context, so the workspace is always set.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $imports,
        private readonly ImportRepository $repository,
        private readonly CatalogAccess $access,
        private readonly CurrentWorkspaceContext $context,
    ) {}

    /** POST /api/workspace/finance/imports/preview */
    public function preview(PreviewImportRequest $request): JsonResponse
    {
        $batch = $this->imports->preview(
            $this->context->workspace(),
            $request->user(),
            $request->file('file'),
            $request->importType(),
        );

        return ApiResponse::success(new ImportBatchResource($batch), __('messages.import_previewed'), 201);
    }

    /** POST /api/workspace/finance/imports/{id}/commit */
    public function commit(Request $request, string $id): JsonResponse
    {
        $workspace = $this->context->workspace();
        $type = $this->repository->findBatch($workspace, $id)->type;
        abort_unless($this->access->canImport($request->user(), $type), 403, __('messages.insufficient_permissions'));

        $batch = $this->imports->commit($workspace, $request->user(), $id);

        return ApiResponse::success(new ImportBatchResource($batch), __('messages.import_committed'));
    }

    /** GET /api/workspace/finance/imports/template/{type} */
    public function template(Request $request, string $type): Response
    {
        $importType = ImportType::tryFrom($type) ?? abort(404);
        abort_unless($this->access->canImport($request->user(), $importType), 403, __('messages.insufficient_permissions'));

        return response($this->imports->template($importType), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$importType->value.'-template.xlsx"',
        ]);
    }
}
