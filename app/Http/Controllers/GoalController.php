<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\GoalLog;
use App\Models\TimeBlock;
use App\Services\GoalAttributionService;
use App\Services\GoalKeywordExtractor;
use App\Services\GoalProbabilityService;
use App\Services\GoalTimeAnalysisService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    public function __construct(
        private GoalProbabilityService $probability,
        private GoalAttributionService $attribution,
        private GoalKeywordExtractor $keywords,
        private GoalTimeAnalysisService $timeAnalysis,
    ) {
    }

    public function index(Request $request)
    {
        $goals = Goal::query()
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'missed' THEN 1 WHEN 'completed' THEN 2 WHEN 'abandoned' THEN 3 ELSE 4 END")
            ->orderBy('target_date')
            ->get();

        $summaries = $goals->map(function (Goal $goal) {
            $result = $this->probability->compute($goal);
            $this->probability->persist($goal, (float) $result['percent']);
            return [
                'goal' => $goal,
                'result' => $result,
                'alert' => $this->probability->alertLevel($goal, $result),
                'narrative' => $this->probability->narrative($goal, $result),
            ];
        });

        return view('goals.index', [
            'summaries' => $summaries,
        ]);
    }

    public function create()
    {
        return view('goals.create', [
            'goal' => null,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateGoalPayload($request);

        $keywords = $this->normalizeKeywords($data['keywords'] ?? null);
        if (empty($keywords)) {
            $keywords = $this->keywords->extract($data['title'], $data['description'] ?? null);
        }

        $goal = DB::transaction(function () use ($data, $request, $keywords) {
            $goal = Goal::create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'start_date' => $data['start_date'],
                'target_date' => $data['target_date'],
                'original_target_date' => $data['target_date'],
                'keywords' => $keywords,
                'status' => 'active',
            ]);

            $this->writeLog($goal, GoalLog::ACTION_CREATED, null, $goal->only([
                'title', 'category', 'start_date', 'target_date', 'keywords',
            ]), null);

            return $goal;
        });

        return redirect()->route('goals.show', $goal)
            ->with('toast', 'Goal created.');
    }

    public function show(Request $request, Goal $goal)
    {
        $this->authorize('view', $goal);

        $result = $this->probability->compute($goal);
        $this->probability->persist($goal, (float) $result['percent']);

        $attribution = $this->attribution->forGoal($goal);

        // For the "no matches" UX: show the user the unique reasons they
        // actually logged inside this goal's window, so they can pick one
        // to add as a keyword with a single click.
        $matchedExternalIds = $attribution['blocks']
            ->pluck('block.external_id')
            ->filter()
            ->values()
            ->all();

        $allReasons = TimeBlock::query()
            ->where('user_id', $goal->user_id)
            ->where('duration_seconds', '>', 0)
            ->whereBetween('start_time', [
                CarbonImmutable::parse($goal->start_date)->startOfDay(),
                CarbonImmutable::parse($goal->target_date)->endOfDay(),
            ])
            ->orderByDesc('start_time')
            ->limit(80)
            ->get(['external_id', 'reason']);

        $reasonSuggestions = $allReasons
            ->map(fn ($b) => trim((string) ($b->reason ?? '')))
            ->filter(fn ($r) => $r !== '')
            ->unique()
            ->take(20)
            ->values();

        $today = Carbon::today();
        $windowStart = CarbonImmutable::parse($goal->start_date)->startOfDay();
        $sparkStart = max(
            $windowStart->toDateString(),
            $today->copy()->subDays(13)->toDateString()
        );

        // Sparkline = sum of attributed hours per day for last 14 days.
        $byDate = $attribution['blocks']
            ->filter(fn ($entry) => $entry['block']->start_time->toDateString() >= $sparkStart)
            ->groupBy(fn ($entry) => $entry['block']->start_time->toDateString())
            ->map(fn ($entries) => $entries->sum('attributed_hours'));

        $sparkline = [];
        $cursor = Carbon::parse($sparkStart);
        $end = $today->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $sparkline[] = [
                'date' => $key,
                'label' => $cursor->format('M j'),
                'hours' => (float) ($byDate[$key] ?? 0.0),
            ];
            $cursor = $cursor->copy()->addDay();
        }

        $matchedBlocks = $attribution['blocks']->take(15);
        $logCount = $goal->logs()->count();

        $timeAnalysis = $this->timeAnalysis->analyze($goal, $request->user());

        return view('goals.show', [
            'goal' => $goal,
            'result' => $result,
            'narrative' => $this->probability->narrative($goal, $result),
            'matchedBlocks' => $matchedBlocks,
            'attribution' => $attribution,
            'reasonSuggestions' => $reasonSuggestions,
            'totalBlocksInWindow' => $allReasons->count(),
            'logCount' => $logCount,
            'sparkline' => $sparkline,
            'today' => $today->toDateString(),
            'timeAnalysis' => $timeAnalysis,
        ]);
    }

    public function edit(Goal $goal)
    {
        $this->authorize('update', $goal);

        return view('goals.edit', [
            'goal' => $goal,
            'today' => now()->toDateString(),
        ]);
    }

    public function update(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $data = $this->validateGoalPayload($request, $goal);

        $reason = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ])['reason'];

        $original = $goal->only([
            'title', 'description', 'category', 'start_date', 'target_date', 'keywords',
        ]);

        $keywords = $this->normalizeKeywords($data['keywords'] ?? null);
        if (empty($keywords)) {
            $keywords = $this->keywords->extract($data['title'], $data['description'] ?? null);
        }

        $newTarget = Carbon::parse($data['target_date']);
        $oldTarget = $goal->target_date->copy();

        $action = GoalLog::ACTION_EDITED;
        $extensionDelta = 0;

        if (! $newTarget->isSameDay($oldTarget)) {
            if ($newTarget->gt($oldTarget)) {
                $action = GoalLog::ACTION_EXTENDED;
                $extensionDelta = 1;
            } else {
                $action = GoalLog::ACTION_SHORTENED;
            }
        }

        DB::transaction(function () use ($goal, $data, $original, $action, $extensionDelta, $reason, $keywords) {
            $goal->fill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'start_date' => $data['start_date'],
                'target_date' => $data['target_date'],
                'keywords' => $keywords,
            ]);

            if ($extensionDelta > 0) {
                $goal->extension_count = $goal->extension_count + $extensionDelta;
            }
            $goal->change_count = $goal->change_count + 1;
            $goal->save();

            $this->writeLog($goal, $action, $original, $goal->only(array_keys($original)), $reason);
        });

        return redirect()->route('goals.show', $goal)
            ->with('toast', $action === GoalLog::ACTION_EXTENDED
                ? 'Goal extended.'
                : 'Goal updated.');
    }

    public function extend(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'target_date' => ['required', 'date', 'after:'.$goal->target_date->toDateString()],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $oldTarget = $goal->target_date->copy();

        DB::transaction(function () use ($goal, $data, $oldTarget) {
            $goal->target_date = $data['target_date'];
            $goal->extension_count = $goal->extension_count + 1;
            $goal->change_count = $goal->change_count + 1;
            if ($goal->status === 'missed') {
                $goal->status = 'active';
            }
            $goal->save();

            $this->writeLog(
                $goal,
                GoalLog::ACTION_EXTENDED,
                ['target_date' => $oldTarget->toDateString()],
                ['target_date' => $goal->target_date->toDateString()],
                $data['reason']
            );
        });

        return redirect()->route('goals.show', $goal)
            ->with('toast', 'Goal extended.');
    }

    public function complete(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        if ($goal->status === 'completed') {
            return redirect()->route('goals.show', $goal);
        }

        DB::transaction(function () use ($goal) {
            $oldStatus = $goal->status;
            $goal->status = 'completed';
            $goal->completed_at = now();
            $goal->save();

            $this->writeLog(
                $goal,
                GoalLog::ACTION_COMPLETED,
                ['status' => $oldStatus],
                ['status' => 'completed', 'completed_at' => $goal->completed_at->toIso8601String()],
                null
            );
        });

        return redirect()->route('goals.show', $goal)
            ->with('toast', 'Goal completed.');
    }

    public function abandon(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($goal, $data) {
            $oldStatus = $goal->status;
            $goal->status = 'abandoned';
            $goal->save();

            $this->writeLog(
                $goal,
                GoalLog::ACTION_ABANDONED,
                ['status' => $oldStatus],
                ['status' => 'abandoned'],
                $data['reason']
            );
        });

        return redirect()->route('goals.index')
            ->with('toast', 'Goal abandoned.');
    }

    /**
     * Append a keyword to the goal (or remove one). Used by the
     * "suggested keywords" UI on the goal show page.
     */
    public function addKeyword(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:60'],
            'remove' => ['nullable', 'boolean'],
        ]);

        $keyword = mb_strtolower(trim($data['keyword']));
        if ($keyword === '') {
            return redirect()->route('goals.show', $goal);
        }

        $current = is_array($goal->keywords) ? $goal->keywords : [];
        $remove = ! empty($data['remove']);

        if ($remove) {
            $next = array_values(array_filter($current, fn ($k) => $k !== $keyword));
        } else {
            if (in_array($keyword, $current, true)) {
                return redirect()->route('goals.show', $goal);
            }
            $next = array_slice(array_merge($current, [$keyword]), 0, 30);
        }

        $original = ['keywords' => $current];
        $goal->keywords = $next;
        $goal->save();

        $this->writeLog(
            $goal,
            GoalLog::ACTION_EDITED,
            $original,
            ['keywords' => $next],
            $remove ? 'Removed keyword: '.$keyword : 'Added keyword: '.$keyword,
        );

        return redirect()->route('goals.show', $goal)
            ->with('toast', $remove ? 'Keyword removed.' : 'Keyword added.');
    }

    public function logs(Goal $goal)
    {
        $this->authorize('view', $goal);

        $logs = $goal->logs()->paginate(50);

        return view('goals.logs', [
            'goal' => $goal,
            'logs' => $logs,
        ]);
    }

    public function destroy(Goal $goal)
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return redirect()->route('goals.index')
            ->with('toast', 'Goal deleted.');
    }

    private function validateGoalPayload(Request $request, ?Goal $existing = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(['exam', 'project', 'fitness', 'learning', 'career', 'personal', 'custom'])],
            'start_date' => ['required', 'date'],
            'target_date' => ['required', 'date', 'after_or_equal:start_date'],
            'keywords' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Accepts either an array of keywords or a comma-separated string and
     * returns a clean lowercase list (deduped, trimmed, max 30 items).
     */
    private function normalizeKeywords(string|array|null $raw): array
    {
        if ($raw === null || $raw === '') return [];
        $items = is_array($raw)
            ? $raw
            : (preg_split('/[,\n]+/u', $raw) ?: []);
        $clean = [];
        foreach ($items as $item) {
            $item = mb_strtolower(trim((string) $item));
            if ($item === '' || mb_strlen($item) > 60) continue;
            $clean[] = $item;
        }
        return array_slice(array_values(array_unique($clean)), 0, 30);
    }

    private function writeLog(Goal $goal, string $action, ?array $oldValue, ?array $newValue, ?string $reason): void
    {
        GoalLog::create([
            'goal_id' => $goal->id,
            'user_id' => $goal->user_id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
        ]);
    }
}
