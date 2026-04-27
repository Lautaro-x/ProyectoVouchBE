<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomTrailerItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:150'],
            'youtube_url' => ['required', 'url', 'regex:/youtube\.com|youtu\.be/'],
        ];
    }
}
