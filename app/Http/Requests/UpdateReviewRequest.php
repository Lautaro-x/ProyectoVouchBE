<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $body = trim($this->input('body', ''));
        $this->merge(['body' => $body !== '' ? $body : null]);
    }

    public function rules(): array
    {
        $canLink    = in_array($this->user()?->role, ['critic', 'admin']);
        $isVerified = in_array('verificado', $this->user()?->badges ?? [], true);

        $bodyRules = ['nullable', 'string', 'max:2000'];

        if (!$canLink) {
            $tlds = 'com|net|org|io|tv|be|co|gg|app|dev|me|info|biz|pro|live|online|site|web|link|media|stream|video|es|uk|fr|de|it|pt|br|mx|ar|jp|ru|ca|au|nl|pl|us|to|fm|ly|gl|sh|ai|gg';

            $detectUrl     = "/(https?:\\/\\/|www\\.|javascript:)[^\\s]+|\\b[a-zA-Z0-9][a-zA-Z0-9\\-]*\\.({$tlds})\\b[^\\s]*/i";
            $allowedDomain = '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|tiktok\.com|twitch\.tv)(\/|$)/i';

            if ($isVerified) {
                $bodyRules[] = function ($attribute, $value, $fail) use ($detectUrl, $allowedDomain) {
                    if (!$value) return;
                    if (preg_match_all($detectUrl, $value, $matches)) {
                        foreach ($matches[0] as $url) {
                            if (!preg_match($allowedDomain, $url)) {
                                $fail('Solo se permiten enlaces de YouTube, TikTok o Twitch.');
                            }
                        }
                    }
                };
            } else {
                $bodyRules[] = function ($attribute, $value, $fail) use ($detectUrl) {
                    if ($value && preg_match($detectUrl, $value)) {
                        $fail('Los enlaces no están permitidos en críticas de usuarios estándar.');
                    }
                };
            }
        }

        return [
            'body'                 => $bodyRules,
            'scores'               => 'required|array|min:1',
            'scores.*.category_id' => 'required|exists:Categories,id',
            'scores.*.score'       => 'required|integer|min:0|max:10',
        ];
    }
}
