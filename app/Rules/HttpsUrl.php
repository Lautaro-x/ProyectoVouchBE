<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL) || parse_url($value, PHP_URL_SCHEME) !== 'https') {
            $fail("El campo :attribute debe ser una URL https válida.");
        }
    }
}
