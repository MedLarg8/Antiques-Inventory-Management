<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StockQuantity implements ValidationRule
{
    /** @phpstan-assert-if-true numeric-string $value */
    public static function valid(mixed $value): bool
    {
        return is_string($value) && is_numeric($value) && preg_match('/^\\d{1,15}(\\.\\d{1,3})?$/D', $value) === 1 && bccomp($value, '0', 3) > 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valid($value)) {
            $fail('Enter a positive decimal quantity with at most 15 whole digits and three decimal places.');
        }
    }
}
