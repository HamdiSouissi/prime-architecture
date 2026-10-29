<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TunisianTaxId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = (string) config('projects.tax_id_pattern');
        if (! is_string($value) || @preg_match($pattern, strtoupper(trim($value))) !== 1) {
            $fail(__('phase3.invalid_tax_id'));
        }
    }
}
