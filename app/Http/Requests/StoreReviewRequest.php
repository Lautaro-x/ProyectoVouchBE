<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $canLink = in_array($this->user()?->role, ['critic', 'admin']);

        return [
            'product_id'           => 'required|exists:Products,id',
            'body'                 => array_filter([
                'nullable', 'string', 'max:2000',
                $canLink ? null : 'not_regex:/(https?:\/\/|www\.)\S+/i',
            ]),
            'scores'               => 'required|array|min:1',
            'scores.*.category_id' => 'required|exists:Categories,id',
            'scores.*.score'       => 'required|integer|min:0|max:10',
        ];
    }
}
