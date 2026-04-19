<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function index(): JsonResponse
    {
        $surveys = Survey::withCount('responses')
            ->with('options')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn($s) => [
                'id'              => $s->id,
                'title'           => $s->getTranslations('title'),
                'starts_at'       => $s->starts_at->toDateTimeString(),
                'ends_at'         => $s->ends_at->toDateTimeString(),
                'responses_count' => $s->responses_count,
                'status'          => $s->status(),
                'audience'        => $s->audience,
            ]);

        return response()->json($surveys);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'        => 'required|array',
            'title.es'     => 'required|string|max:255',
            'title.en'     => 'required|string|max:255',
            'title.fr'     => 'required|string|max:255',
            'title.pt'     => 'required|string|max:255',
            'title.it'     => 'required|string|max:255',
            'question'     => 'required|array',
            'question.es'  => 'required|string',
            'question.en'  => 'required|string',
            'question.fr'  => 'required|string',
            'question.pt'  => 'required|string',
            'question.it'  => 'required|string',
            'starts_at'    => 'required|date',
            'ends_at'      => 'required|date|after:starts_at',
            'audience'     => 'required|in:all,verified,press',
            'options'      => 'required|array|min:2',
            'options.*.es' => 'required|string|max:255',
            'options.*.en' => 'required|string|max:255',
            'options.*.fr' => 'required|string|max:255',
            'options.*.pt' => 'required|string|max:255',
            'options.*.it' => 'required|string|max:255',
        ]);

        $survey = Survey::create([
            'title'     => $data['title'],
            'question'  => $data['question'],
            'starts_at' => $data['starts_at'],
            'ends_at'   => $data['ends_at'],
            'audience'  => $data['audience'],
        ]);

        foreach ($data['options'] as $i => $textByLang) {
            $survey->options()->create(['text' => $textByLang, 'order' => $i]);
        }

        return response()->json($this->formatSurvey($survey->load('options')), 201);
    }

    public function show(Survey $survey): JsonResponse
    {
        $survey->load('options');
        return response()->json($this->formatSurvey($survey));
    }

    public function update(Request $request, Survey $survey): JsonResponse
    {
        $data = $request->validate([
            'title'        => 'required|array',
            'title.es'     => 'required|string|max:255',
            'title.en'     => 'required|string|max:255',
            'title.fr'     => 'required|string|max:255',
            'title.pt'     => 'required|string|max:255',
            'title.it'     => 'required|string|max:255',
            'question'     => 'required|array',
            'question.es'  => 'required|string',
            'question.en'  => 'required|string',
            'question.fr'  => 'required|string',
            'question.pt'  => 'required|string',
            'question.it'  => 'required|string',
            'starts_at'    => 'required|date',
            'ends_at'      => 'required|date|after:starts_at',
            'audience'     => 'required|in:all,verified,press',
            'options'      => 'required|array|min:2',
            'options.*.es' => 'required|string|max:255',
            'options.*.en' => 'required|string|max:255',
            'options.*.fr' => 'required|string|max:255',
            'options.*.pt' => 'required|string|max:255',
            'options.*.it' => 'required|string|max:255',
        ]);

        $survey->update([
            'title'     => $data['title'],
            'question'  => $data['question'],
            'starts_at' => $data['starts_at'],
            'ends_at'   => $data['ends_at'],
            'audience'  => $data['audience'],
        ]);

        $survey->options()->delete();
        foreach ($data['options'] as $i => $textByLang) {
            $survey->options()->create(['text' => $textByLang, 'order' => $i]);
        }

        return response()->json($this->formatSurvey($survey->load('options')));
    }

    public function results(Survey $survey): JsonResponse
    {
        $options = $survey->options()->withCount('responses')->get();
        $total   = $options->sum('responses_count');

        return response()->json([
            'total'   => $total,
            'options' => $options->map(fn($o) => [
                'id'      => $o->id,
                'text'    => $o->getTranslations('text'),
                'count'   => $o->responses_count,
                'percent' => $total > 0 ? round($o->responses_count / $total * 100, 1) : 0,
            ]),
        ]);
    }

    public function destroy(Survey $survey): JsonResponse
    {
        $survey->delete();
        return response()->json(null, 204);
    }

    private function formatSurvey(Survey $survey): array
    {
        return [
            'id'        => $survey->id,
            'title'     => $survey->getTranslations('title'),
            'question'  => $survey->getTranslations('question'),
            'starts_at' => $survey->starts_at->toDateTimeString(),
            'ends_at'   => $survey->ends_at->toDateTimeString(),
            'audience'  => $survey->audience,
            'options'   => $survey->options->map(fn($o) => [
                'id'    => $o->id,
                'text'  => $o->getTranslations('text'),
                'order' => $o->order,
            ]),
        ];
    }
}
