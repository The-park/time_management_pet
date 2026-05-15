<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminQuoteController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));

        // Admin panel manages ONLY the global/curated quote pool. User-
        // created quotes (user_id IS NOT NULL) belong to their owner and
        // are managed at /quotes — never expose them here, even for
        // search / counts.
        $query = Quote::query()->whereNull('user_id');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('text', 'like', '%'.$search.'%')
                    ->orWhere('author', 'like', '%'.$search.'%')
                    ->orWhere('source', 'like', '%'.$search.'%');
            });
        }
        if ($category !== '' && in_array($category, Quote::ALLOWED_CATEGORIES, true)) {
            $query->where('category', $category);
        }

        return view('admin.quotes.index', [
            'quotes' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'search' => $search,
            'category' => $category,
            'categories' => Quote::ALLOWED_CATEGORIES,
            'total' => Quote::query()->whereNull('user_id')->count(),
            'activeCount' => Quote::query()->whereNull('user_id')->where('is_active', true)->count(),
        ]);
    }

    public function create()
    {
        return view('admin.quotes.create', [
            'categories' => Quote::ALLOWED_CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        // Unchecked HTML checkboxes are omitted from the request body; we
        // must default to false here so an admin who unticks "Active"
        // during create actually saves the quote as inactive. Matches the
        // update() path below.
        $data['is_active'] = $request->boolean('is_active', false);
        // Explicit: admin-created quotes ALWAYS belong to the global
        // pool, never to a user.
        $data['user_id'] = null;

        $quote = Quote::create($data);
        AdminAudit::log('created_quote', null, ['id' => $quote->id]);

        return redirect()
            ->route('admin.quotes.index')
            ->with('toast', 'Quote added.');
    }

    public function edit($id)
    {
        // Scope by user_id IS NULL so admins can't accidentally land on
        // a user-owned quote's edit page (would 404 instead of leaking).
        $quote = Quote::query()->whereNull('user_id')->findOrFail($id);

        return view('admin.quotes.edit', [
            'quote' => $quote,
            'categories' => Quote::ALLOWED_CATEGORIES,
        ]);
    }

    public function update(Request $request, $id)
    {
        $quote = Quote::query()->whereNull('user_id')->findOrFail($id);

        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', false);

        $quote->update($data);
        AdminAudit::log('updated_quote', null, ['id' => $quote->id]);

        return redirect()
            ->route('admin.quotes.index')
            ->with('toast', 'Quote updated.');
    }

    public function toggleActive($id)
    {
        $quote = Quote::query()->whereNull('user_id')->findOrFail($id);
        $quote->is_active = ! $quote->is_active;
        $quote->save();
        AdminAudit::log('toggled_quote', null, ['id' => $quote->id, 'is_active' => $quote->is_active]);

        return redirect()
            ->route('admin.quotes.index')
            ->with('toast', $quote->is_active ? 'Quote activated.' : 'Quote disabled.');
    }

    public function destroy($id)
    {
        $quote = Quote::query()->whereNull('user_id')->findOrFail($id);
        $quote->delete();
        AdminAudit::log('deleted_quote', null, ['id' => (int) $id]);

        return redirect()
            ->route('admin.quotes.index')
            ->with('toast', 'Quote deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'text' => ['required', 'string', 'min:5', 'max:500'],
            'author' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(Quote::ALLOWED_CATEGORIES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
