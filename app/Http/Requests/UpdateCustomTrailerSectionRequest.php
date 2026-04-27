<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomTrailerSectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'     => ['required', 'array'],
            'title.*'   => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
