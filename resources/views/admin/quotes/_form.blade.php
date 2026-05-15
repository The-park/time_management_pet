@php($quote = $quote ?? null)

<div>
    <label for="text" class="block text-xs uppercase tracking-[0.15em] text-slate-400 mb-1.5">Quote text</label>
    <textarea id="text" name="text" required rows="3" maxlength="500"
        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20"
        placeholder="The pain you feel today will be the strength you feel tomorrow.">{{ old('text', $quote?->text) }}</textarea>
    @error('text')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
    <p class="mt-1 text-[0.65rem] text-slate-500">Between 5 and 500 characters.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div>
        <label for="author" class="block text-xs uppercase tracking-[0.15em] text-slate-400 mb-1.5">Author <span class="text-slate-600 normal-case tracking-normal">(optional)</span></label>
        <input id="author" name="author" type="text" maxlength="120" value="{{ old('author', $quote?->author) }}"
            placeholder="Eren Yeager"
            class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
        @error('author')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="source" class="block text-xs uppercase tracking-[0.15em] text-slate-400 mb-1.5">Source <span class="text-slate-600 normal-case tracking-normal">(optional)</span></label>
        <input id="source" name="source" type="text" maxlength="120" value="{{ old('source', $quote?->source) }}"
            placeholder="Attack on Titan"
            class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
        @error('source')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="category" class="block text-xs uppercase tracking-[0.15em] text-slate-400 mb-1.5">Category</label>
    <select id="category" name="category" required
        class="w-full rounded-lg bg-slate-950/60 border border-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-rose-500/40 focus:outline-none focus:ring-1 focus:ring-rose-500/20">
        @foreach ($categories as $c)
            <option value="{{ $c }}" @selected(old('category', $quote?->category) === $c)>{{ ucfirst($c) }}</option>
        @endforeach
    </select>
    @error('category')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
</div>

<label class="inline-flex items-center gap-2 cursor-pointer pt-1">
    <input type="checkbox" name="is_active" value="1"
        @checked(old('is_active', $quote?->is_active ?? true))
        class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-emerald-500 focus:ring-emerald-500/40">
    <span class="text-sm text-slate-200">Active <span class="text-xs text-slate-500">(eligible for display)</span></span>
</label>
