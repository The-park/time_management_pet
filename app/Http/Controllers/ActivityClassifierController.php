<?php

namespace App\Http\Controllers;

use App\Services\ActivityClassifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer over ActivityClassifierService:
 *
 *   POST /classify           — classify a text → 'productive' | 'unproductive'
 *   POST /classify/feedback  — submit a user correction → retrain + persist
 *
 * The service is dependency-injected (Laravel resolves it via the container)
 * so the model can be loaded once per worker and reused — no per-request
 * retraining, ever.
 */
class ActivityClassifierController extends Controller
{
    public function __construct(private ActivityClassifierService $classifier)
    {
    }

    /**
     * Classify a single activity description.
     *
     * Request body: { "text": "studied chapter 5 of the security book" }
     * Response:     {
     *   "ok": true,
     *   "text": "...",
     *   "label": "productive" | "unproductive" | "ambiguous",
     *   "is_ambiguous": bool,
     *   "reason": "conflict" | "truncation" | "hedge" | "lexicon-short" | "naive-bayes",
     *   "detail": string|null,            // human-readable explanation when ambiguous
     *   "candidates": [string, ...]       // labels the user might pick if ambiguous
     * }
     *
     * When `is_ambiguous` is true, the UI should ask the user to pick a
     * concrete label rather than auto-applying one — the model is
     * explicitly saying it cannot decide without their input.
     */
    public function classify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $detail = $this->classifier->classifyDetailed($data['text']);

        return response()->json([
            'ok'           => true,
            'text'         => $data['text'],
            'label'        => $detail['label'],
            'is_ambiguous' => $detail['label'] === ActivityClassifierService::AMBIGUOUS,
            'reason'       => $detail['reason'],
            'detail'       => $detail['detail'] ?? null,
            'candidates'   => $detail['candidates'],
        ]);
    }

    /**
     * User-supplied correction. Adds the example to the persisted feedback
     * corpus, retrains the model, and returns the corpus stats so the UI
     * can show "trained on N examples".
     *
     * Request body: { "text": "...", "expected": "productive" | "unproductive" }
     */
    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text'     => ['required', 'string', 'max:2000'],
            'expected' => [
                'required',
                'string',
                'in:'.ActivityClassifierService::PRODUCTIVE
                    .','.ActivityClassifierService::UNPRODUCTIVE
                    .','.ActivityClassifierService::AMBIGUOUS,
            ],
        ]);

        $this->classifier->recordFeedback($data['text'], $data['expected']);

        return response()->json([
            'ok'    => true,
            'label' => $this->classifier->predict($data['text']),  // confirm it stuck
            'stats' => $this->classifier->corpusStats(),
        ]);
    }
}
