<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A message submitted through the public "Contact us" form. Stored for the admin
 * inbox; the admin replies out-of-band (email) since the sender may be a guest.
 */
class ContactMessage extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'is_read',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }
}
