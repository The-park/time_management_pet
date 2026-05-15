<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;

class QuoteController extends Controller
{
    /**
     * Random quote for the flying-quote bubble. Respects the authed
     * user's `quote_source` preference so that picking "mine" never
     * surfaces an admin quote, and vice versa.
     *
     * Falls back to a curated motivational string when the user's chosen
     * pool is empty (e.g. they selected "mine" but haven't added any
     * quotes yet) — keeps the bubble alive instead of returning nulls
     * the front-end would silently drop.
     */
    public function random(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user?->id;
        $source = $user?->quoteSource() ?? 'mixed';

        $quote = Quote::active()
            ->forFeed($userId, $source)
            ->inRandomOrder()
            ->first();

        if (! $quote) {
            return response()->json([
                'text' => 'Keep going.',
                'author' => null,
                'source' => null,
                'category' => 'other',
            ]);
        }

        return response()->json([
            'text' => $quote->text,
            'author' => $quote->author,
            'source' => $quote->source,
            'category' => $quote->category,
        ]);
    }

    /**
     * Personal-quote management page. Lists ONLY the authed user's own
     * quotes (user_id = me); admin quotes live in /admin/quotes and are
     * never shown here as editable. We still expose a read-only count
     * of admin quotes available so the user knows the global pool size.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $quotes = Quote::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        $adminCount = Quote::query()
            ->whereNull('user_id')
            ->where('is_active', true)
            ->count();

        return view('quotes.index', [
            'quotes' => $quotes,
            'categories' => Quote::ALLOWED_CATEGORIES,
            'adminCount' => $adminCount,
            'myCount' => $quotes->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['user_id'] = $request->user()->id;

        Quote::create($data);

        return redirect()
            ->route('quotes.index')
            ->with('toast', 'Quote added.');
    }

    public function update(Request $request, Quote $quote)
    {
        $this->authorize('update', $quote);

        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', false);

        // Never let an update reassign ownership — strip user_id if a
        // forged input slipped in.
        unset($data['user_id']);

        $quote->update($data);

        return redirect()
            ->route('quotes.index')
            ->with('toast', 'Quote updated.');
    }

    public function toggleActive(Request $request, Quote $quote)
    {
        $this->authorize('update', $quote);

        $quote->is_active = ! $quote->is_active;
        $quote->save();

        return redirect()
            ->route('quotes.index')
            ->with('toast', $quote->is_active ? 'Quote resumed.' : 'Quote paused.');
    }

    public function destroy(Request $request, Quote $quote)
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return redirect()
            ->route('quotes.index')
            ->with('toast', 'Quote deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'text' => ['required', 'string', 'min:5', 'max:500'],
            'author' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'category' => ['required', ValidationRule::in(Quote::ALLOWED_CATEGORIES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
