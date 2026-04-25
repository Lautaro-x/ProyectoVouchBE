<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body'                 => 'nullable|string|max:2000',
            'scores'               => 'required|array|min:1',
            'scores.*.category_id' => 'required|exists:Categories,id',
            'scores.*.score'       => 'required|integer|min:0|max:10',
        ];
    }
}
