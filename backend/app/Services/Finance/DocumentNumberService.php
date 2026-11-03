<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Hands out gap-free document numbers. The counter row is locked for the rest
 * of the caller's transaction, so concurrent issuers queue up and a rolled-back
 * issue gives its number back instead of leaving a hole.
 */
class DocumentNumberService
{
    private const PAD_LENGTH = 6;

    public function next(Workspace $workspace, DocumentType $type = DocumentType::Invoice): string
    {
        return DB::transaction(function () use ($workspace, $type): string {
            $sequence = $this->lockedSequence($workspace, $type);
            $value = $sequence->next_value;

            $sequence->forceFill(['next_value' => $value + 1])->save();

            return $sequence->prefix.str_pad((string) $value, self::PAD_LENGTH, '0', STR_PAD_LEFT);
        });
    }

    private function lockedSequence(Workspace $workspace, DocumentType $type): DocumentSequence
    {
        $find = fn (): ?DocumentSequence => DocumentSequence::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', $type->value)
            ->lockForUpdate()
            ->first();

        $existing = $find();
        if ($existing !== null) {
            return $existing;
        }

        try {
            DocumentSequence::query()->create([
                'workspace_id' => $workspace->id,
                'type' => $type->value,
                'prefix' => $this->prefixFor($workspace, $type),
                'next_value' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent issuer created the row first; lock theirs below.
        }

        return $find() ?? throw new \LogicException('Document sequence could not be created.');
    }

    /**
     * Workspace-readable and unique: ASCII letters of the name plus a slice of
     * the id, so two workspaces with the same name never share numbers.
     */
    private function prefixFor(Workspace $workspace, DocumentType $type): string
    {
        $name = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $workspace->name), 0, 6));
        $idPart = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $workspace->id), 0, 4));

        return ($name === '' ? 'WS' : $name).'-'.$idPart.'-'.strtoupper(substr($type->value, 0, 3)).'-';
    }
}
