<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\FiltersAudience;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    use ApiResponse, FiltersAudience;

    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        $now  = now();

        $surveys = Survey::where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereDoesntHave('responses', fn($q) => $q->where('user_id', $user->id))
            ->with('options')
            ->get()
            ->filter(fn($s) => $s->hasAllTranslations() && $this->userMatchesAudience($user, $s->audience))
            ->values()
            ->map(fn($s) => [
                'id'       => $s->id,
                'title'    => $s->getTranslations('title'),
                'question' => $s->getTranslations('question'),
                'audience' => $s->audience,
                'options'  => $s->options->map(fn($o) => [
                    'id'   => $o->id,
                    'text' => $o->getTranslations('text'),
                ]),
            ]);

        return $this->ok($surveys);
    }

    public function respond(Request $request, Survey $survey): JsonResponse
    {
        $data = $request->validate([
            'option_id' => 'required|exists:survey_options,id',
        ]);

        if ($survey->options()->where('id', $data['option_id'])->doesntExist()) {
            return $this->error('Invalid option');
        }

        $alreadyResponded = SurveyResponse::where('survey_id', $survey->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadyResponded) {
            return $this->error('Already responded');
        }

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'user_id'   => $request->user()->id,
            'option_id' => $data['option_id'],
        ]);

        return $this->ok();
    }
}
