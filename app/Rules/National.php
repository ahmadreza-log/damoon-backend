<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Iranian national id (کد ملی).
 *
 * Ten digits, and the last digit must match the official checksum.
 * An empty value is allowed by the nullable rule on the field. This rule
 * only rejects a value that was actually typed.
 *
 * Extending:
 * - Laravel owns the validate method name.
 * - Keep the message Persian. The panel has no other validation language file.
 */
class National implements ValidationRule
{
    /**
     * Rejects a national id that is not ten digits or fails the checksum.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{10}$/', $value)) {
            $fail('کد ملی باید ۱۰ رقم باشد.');

            return;
        }

        if (preg_match('/^(\d)\1{9}$/', $value) === 1) {
            $fail('کد ملی معتبر نیست.');

            return;
        }

        $sum = 0;

        for ($place = 0; $place < 9; $place++) {
            $sum += (int) $value[$place] * (10 - $place);
        }

        $remain = $sum % 11;
        $check = (int) $value[9];
        $valid = $remain < 2 ? $check === $remain : $check === (11 - $remain);

        if (! $valid) {
            $fail('کد ملی معتبر نیست.');
        }
    }
}
