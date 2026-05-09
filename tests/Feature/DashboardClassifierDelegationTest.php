<?php

/**
 * Regression test for the dual-classifier bug:
 * The dashboard had two parallel classifiers — the rich `analyzeLabel()`
 * powering the real-time hint, and the legacy keyword-only
 * `categorizeLabel()` driving the SAVE flow. They diverged: hint said
 * WASTED, saved chip said PRODUCTIVE.
 *
 * This test reads the dashboard view source and asserts that
 * `categorizeLabel` delegates to `analyzeLabel`. We don't have a JS
 * unit-test stack, so a static-text grep is the cheapest way to lock
 * in the fix and prevent the next person from accidentally restoring
 * the legacy implementation.
 */

test('dashboard categorizeLabel delegates to analyzeLabel for category mapping', function () {
    $path = resource_path('views/dashboard.blade.php');
    expect(is_file($path))->toBeTrue();

    $source = file_get_contents($path);

    // Single source of truth: categorizeLabel must call analyzeLabel.
    // Without this, the save flow falls back to keyword-only scoring
    // and re-introduces the dual-classifier divergence.
    expect($source)
        ->toContain('const categorizeLabel = (label) =>')
        ->toContain('analyzeLabel(label)')
        // The 'wasted' path must come from analyzeLabel's category, not
        // from a separate WASTED_TOKENS scoring loop running first.
        ->toMatch('/categorizeLabel[\s\S]{0,400}analyzeLabel\(label\)[\s\S]{0,200}wasted/');
});

test('dashboard exposes the fakeProductive guard in detectClearVerdict', function () {
    // The fakeProductive guard is the round-23 fix that prevents
    // "wrote zero lines" / "color coded" / "X instead of Y" from
    // looking like productive verdicts. Lock its presence in.
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));
    expect($source)->toContain('fakeProductive');
});

test('dashboard gibberish branch maps to wasted (not unknown/productive)', function () {
    // Regression: gibberish inputs ("sssss dss fdsdrs asd asd awdaesd")
    // were saving as PRODUCTIVE because the analyzeLabel gibberish
    // branch returned 'unknown' which categorizeLabel mapped to
    // productive. Now the branch returns 'wasted' directly.
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));
    expect($source)->toMatch("/if\s*\(\s*looksLikeGibberish\s*\)[\s\S]{0,800}result\.category\s*=\s*'wasted'/");
});

test('dashboard isLikelyGibberishToken handles repeated-substring patterns', function () {
    // Regression: tokens like "qweqweqwe" and "asdasdasd" weren't
    // detected because the original detector only checked vowel ratio
    // and length. The repeated-substring rule catches this class.
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));
    // The exact regex must be present in dashboard JS
    expect($source)->toContain('^(\w{2,4})\1{1,}$');
});

test('dashboard exposes the mixed-gibberish ambiguity branch', function () {
    // Round-26 fix: gibberish + real content routes to AMBIGUOUS
    // (modal flow) instead of saving as wasted.
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));
    expect($source)->toContain("warnings.push('mixed-gibberish')");
});

test('dashboard exposes the failed-intent + extended-unprod rule', function () {
    // Round-25 fix: phrases like "wanted to study but played game for
    // hours so whole day" must short-circuit to wasted at SAVE time
    // (not just in the hint). The relevant regex token in
    // detectClearVerdict's failed-intent guard.
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));
    expect($source)->toContain("'wasted'");
    expect($source)->toMatch('/wanted\|tried\|planned\|meant\|intended[\s\S]{0,800}played\|scrolled\|watched\|binged/');
});

test('dashboard migration IIFE preserves ambiguous instead of forcing productive', function () {
    // Round-27 fix: previously the migration IIFE called categorizeLabel
    // on every non-manual block. categorizeLabel only preserves 'wasted'
    // and falls back to 'productive' for everything else, so ambiguous
    // mixed-gibberish blocks (e.g. "ert eeeeeeeee dfdf sd and started
    // studying for 5 hrs") were being silently locked into PRODUCTIVE
    // — the modal never fired and the user never got to split the time.
    //
    // The fix: migration IIFE checks analyzeLabel first; if 'ambiguous',
    // sets block.ambiguityPending and skips categorizeLabel. A separate
    // drainAmbiguityQueue() fires the resolution modal on next render.
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));
    expect($source)->toContain('ambiguityPending');
    expect($source)->toContain('drainAmbiguityQueue');
    // The migration block must call analyzeLabel (not just categorizeLabel)
    // to detect ambiguous before falling through to the binary mapping.
    expect($source)->toMatch('/categoryManual !== true[\s\S]{0,400}analyzeLabel\([^)]*\)[\s\S]{0,400}ambiguityPending\s*=\s*true/');
});

test('dashboard ambiguity modal handles retroactive existingBlockId', function () {
    // Round-27 fix: the modal resolvers (split + pick) must distinguish
    // between first-time saves (no existingBlockId) and retroactive
    // prompts (existingBlockId set on an already-stored block). The
    // retroactive path must mutate the stored block via update() or
    // remove() — never duplicate it via add()/addWithSplit().
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));
    expect($source)->toContain('existingBlockId');
    // The pick path must call update with categoryManual:true on the
    // existing block instead of creating a new one.
    expect($source)->toMatch('/existingBlockId[\s\S]{0,400}update\([^,]+,\s*\{[\s\S]{0,200}categoryManual:\s*true/');
    // The split path must remove the existing block before addWithSplit.
    expect($source)->toMatch('/existingBlockId[\s\S]{0,400}remove\([^)]+\)[\s\S]{0,400}addWithSplit/');
});
