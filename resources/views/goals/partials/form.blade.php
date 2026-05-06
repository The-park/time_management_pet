@php
    $g = $goal;
    $title = old('title', $g?->title);
    $description = old('description', $g?->description);
    $category = old('category', $g?->category ?? 'exam');
    $startDate = old('start_date', $g?->start_date?->toDateString() ?? $today);
    $targetDate = old('target_date', $g?->target_date?->toDateString());
    $existingKeywords = $g && is_array($g->keywords) ? $g->keywords : [];
    $keywordsValue = old('keywords', implode(', ', $existingKeywords));

    $categories = [
        'exam' => 'Exam',
        'project' => 'Project',
        'fitness' => 'Fitness',
        'learning' => 'Learning',
        'career' => 'Career',
        'personal' => 'Personal',
        'custom' => 'Custom',
    ];
@endphp

<section class="chrono-panel rounded-2xl p-6 md:p-8">
    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Goal</h2>
    <p class="text-xs text-slate-500 mb-5">Title, category, optional description.</p>

    <div class="space-y-4">
        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="goal_title">Title</label>
            <input id="goal_title" name="title" type="text" required maxlength="160"
                value="{{ $title }}"
                placeholder="Complete the AWS certification exam"
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
            @error('title')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="goal_category">Category</label>
            <select id="goal_category" name="category" required
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="goal_description">Description</label>
            <textarea id="goal_description" name="description" rows="3" maxlength="2000"
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100"
                placeholder="What does success look like?">{{ $description }}</textarea>
            @error('description')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="chrono-panel rounded-2xl p-6 md:p-8">
    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Timeline</h2>
    <p class="text-xs text-slate-500 mb-5">
        Set the start and target dates. The countdown and probability use these directly —
        progress comes from the hourly logs you already record on the dashboard.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="goal_start">Start date</label>
            <input id="goal_start" name="start_date" type="date" required
                value="{{ $startDate }}"
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
            @error('start_date')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs uppercase tracking-[0.2em] text-slate-400 mb-1" for="goal_target">Target date</label>
            <input id="goal_target" name="target_date" type="date" required
                value="{{ $targetDate }}"
                class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
            @error('target_date')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>
    </div>
</section>

<section class="chrono-panel rounded-2xl p-6 md:p-8">
    <h2 class="font-display text-sm uppercase tracking-[0.3em] text-slate-300 mb-1">Keywords</h2>
    <p class="text-xs text-slate-500 mb-3">
        Comma-separated. When you log a time block on the dashboard, any block whose reason
        mentions one of these words is attributed to this goal. If you have two goals (say AWS
        and CEH) running in parallel, this is how the system knows which hours belong to which.
    </p>
    <p class="text-xs text-slate-500 mb-4">
        Leave blank to auto-extract from the title — you can refine later.
        Examples for AWS: <span class="text-slate-300">aws, ec2, iam, vpc, lambda, s3</span>.
        For CEH: <span class="text-slate-300">ceh, ethical hacker, metasploit, nmap, pentest</span>.
    </p>
    <input id="goal_keywords" name="keywords" type="text" maxlength="1000"
        value="{{ $keywordsValue }}"
        placeholder="aws, ec2, iam, lambda, s3"
        class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-100">
    @error('keywords')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    @if (! empty($existingKeywords))
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach ($existingKeywords as $kw)
                <span class="inline-flex items-center rounded-full border border-[var(--chrono-blue)]/30 bg-[var(--chrono-blue)]/10 px-2 py-0.5 text-xs text-[var(--chrono-blue)]">
                    {{ $kw }}
                </span>
            @endforeach
        </div>
    @endif
</section>
