<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RuleController extends Controller
{
    public function index()
    {
        return view('rules.index', [
            'rules' => Rule::ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        // Append to the bottom: next sort_order = current max + 1.
        $nextOrder = (int) Rule::max('sort_order') + 1;

        Rule::create([
            'user_id' => $request->user()->id,
            'text' => $data['text'],
            'sort_order' => $nextOrder,
            'is_active' => true,
        ]);

        return redirect()->route('rules.index')
            ->with('toast', 'Rule added.');
    }

    public function update(Request $request, Rule $rule)
    {
        $this->authorize('update', $rule);

        $data = $request->validate([
            'text' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $rule->text = $data['text'];
        $rule->save();

        return redirect()->route('rules.index')
            ->with('toast', 'Rule updated.');
    }

    public function toggleActive(Rule $rule)
    {
        $this->authorize('update', $rule);

        $rule->is_active = ! $rule->is_active;
        $rule->save();

        return redirect()->route('rules.index')
            ->with('toast', $rule->is_active ? 'Rule resumed.' : 'Rule paused.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids' => ['array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $data['ids'] ?? [];
        $userId = $request->user()->id;

        DB::transaction(function () use ($ids, $userId) {
            foreach ($ids as $position => $id) {
                // Scope the update by user_id so a forged id can't move
                // someone else's rule. The global BelongsToUser scope
                // already filters Eloquent queries, but raw updates need
                // the explicit guard.
                Rule::where('id', $id)
                    ->where('user_id', $userId)
                    ->update(['sort_order' => $position]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(Rule $rule)
    {
        $this->authorize('delete', $rule);

        $rule->delete();

        return redirect()->route('rules.index')
            ->with('toast', 'Rule deleted.');
    }
}
