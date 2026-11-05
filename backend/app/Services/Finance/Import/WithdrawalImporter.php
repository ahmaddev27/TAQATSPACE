<?php

declare(strict_types=1);

namespace App\Services\Finance\Import;

use App\Enums\ImportType;
use App\Enums\UnbilledChargeSource;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Eloquent\ImportRepository;
use App\Services\Finance\Import\Concerns\ChecksImportCells;
use App\Services\Finance\UnbilledChargeService;
use Carbon\Carbon;
use Throwable;

/**
 * Withdrawals: on-account charges recorded with source `import`. The
 * `source_ref` is the natural key, so a ref that was already imported is
 * rejected. The customer is found by email among the workspace's customers.
 */
class WithdrawalImporter implements RowImporter
{
    use ChecksImportCells;

    public function __construct(
        private readonly ImportRepository $repository,
        private readonly UnbilledChargeService $charges,
    ) {}

    public function type(): ImportType
    {
        return ImportType::Withdrawals;
    }

    public function columns(): array
    {
        return ['source_ref', 'customer_email', 'description', 'qty', 'unit_price', 'occurred_at'];
    }

    public function templateRows(): array
    {
        return [['WD-0001', 'member@example.com', 'Printing', '10', '0.50', '2026-01-31']];
    }

    public function validate(Workspace $workspace, array $rows): array
    {
        $existing = $this->repository->existingImportRefs($workspace, $this->column($rows, 'source_ref'));
        $customers = $this->repository->customerIdsByEmail($workspace, $this->emails($rows));
        $errors = $this->duplicatesInFile($rows, 'source_ref');

        foreach ($rows as $row) {
            array_push($errors, ...$this->rowErrors($row, $existing, $customers));
        }

        usort($errors, static fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return $errors;
    }

    public function apply(Workspace $workspace, User $actor, array $rows): int
    {
        $customers = $this->repository->customerIdsByEmail($workspace, $this->emails($rows));

        foreach ($rows as $row) {
            $values = $row['values'];
            $this->charges->record($workspace, [
                'customer_id' => $customers[strtolower($values['customer_email'])],
                'description' => $values['description'],
                'qty' => $values['qty'],
                'unit_price' => $values['unit_price'],
                'source' => UnbilledChargeSource::Import->value,
                'source_ref' => $values['source_ref'],
                'occurred_at' => $this->occurredAt($values['occurred_at']) ?? now(),
            ], $actor);
        }

        return count($rows);
    }

    /**
     * @param  array{row: int, values: array<string, string>}  $row
     * @param  array<string, true>  $existing
     * @param  array<string, string>  $customers
     * @return list<array{row: int, column: string|null, message: string}>
     */
    private function rowErrors(array $row, array $existing, array $customers): array
    {
        $number = $row['row'];
        $values = $row['values'];
        $ref = $this->checkText($number, 'source_ref', $values['source_ref'], 100);

        return array_values(array_filter([
            $ref ?? (isset($existing[strtolower($values['source_ref'])]) ? $this->error($number, 'source_ref', 'import_row_ref_exists') : null),
            $this->checkText($number, 'customer_email', $values['customer_email'], 255)
                ?? (isset($customers[strtolower($values['customer_email'])]) ? null : $this->error($number, 'customer_email', 'import_row_unknown_customer')),
            $this->checkText($number, 'description', $values['description'], 255),
            $this->checkAmount($number, 'qty', $values['qty'], true),
            $this->checkAmount($number, 'unit_price', $values['unit_price'], false),
            $values['occurred_at'] !== '' && $this->occurredAt($values['occurred_at']) === null ? $this->error($number, 'occurred_at', 'import_row_invalid_date') : null,
        ]));
    }

    /**
     * @param  list<array{row: int, values: array<string, string>}>  $rows
     * @return list<string>
     */
    private function emails(array $rows): array
    {
        return array_values(array_unique(array_map('strtolower', $this->column($rows, 'customer_email'))));
    }

    /** Accepts `Y-m-d`, `Y-m-d H:i[:s]` or a spreadsheet date serial; null when blank or unreadable. */
    private function occurredAt(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            return $serial >= 1 && $serial < 80000 ? Carbon::create(1899, 12, 30)->addSeconds((int) round($serial * 86400)) : null;
        }

        foreach (['Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
