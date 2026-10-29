<?php

namespace App\Rules;

use App\Services\TunisianPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TunisianPhoneRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || TunisianPhone::normalize($value) === null) {
            $fail(__('phase3.invalid_phone'));
        }
    }
}
