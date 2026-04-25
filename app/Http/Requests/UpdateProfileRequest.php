<?php

namespace App\Http\Requests;

use App\Rules\HttpsUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                   => 'sometimes|string|max:25',
            'avatar'                 => ['sometimes', 'nullable', new HttpsUrl(), 'max:500'],
            'social_links'           => 'sometimes|nullable|array',
            'social_links.*.url'     => ['nullable', new HttpsUrl(), 'max:500'],
            'social_links.*.shared'  => 'boolean',
            'card_big_bg'            => ['sometimes', 'nullable', new HttpsUrl(), 'max:500'],
            'card_mid_bg'            => ['sometimes', 'nullable', new HttpsUrl(), 'max:500'],
            'card_mini_bg'           => ['sometimes', 'nullable', new HttpsUrl(), 'max:500'],
        ];
    }
}
