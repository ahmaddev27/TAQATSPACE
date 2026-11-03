<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** ISO 13616 IBAN: shape check plus the mod-97 checksum. */
class Iban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail(__('messages.invalid_iban'));
        }
    }

    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $value));
    }

    public static function isValid(string $value): bool
    {
        $iban = self::normalize($value);

        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = preg_replace_callback(
            '/[A-Z]/',
            static fn (array $m): string => (string) (ord($m[0]) - 55),
            $rearranged,
        );

        return self::mod97((string) $numeric) === 1;
    }

    /** Chunked modulo so the long digit string never overflows an int. */
    private static function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }
}
