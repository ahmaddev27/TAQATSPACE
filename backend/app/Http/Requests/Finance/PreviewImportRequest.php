<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\ImportType;
use App\Services\Finance\CatalogAccess;
use App\Services\Finance\Import\XlsxReader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An import upload. The size cap is enforced here, before the file is opened;
 * the content (single sheet, row count, no formulas) is checked by the reader.
 */
class PreviewImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $type = ImportType::tryFrom((string) $this->input('type'));

        return $user !== null && ($type === null || app(CatalogAccess::class)->canImport($user, $type));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ImportType::class)],
            'file' => ['required', 'file', 'extensions:xlsx', 'max:'.(XlsxReader::MAX_BYTES / 1024)],
        ];
    }

    public function importType(): ImportType
    {
        return ImportType::from($this->validated('type'));
    }
}
