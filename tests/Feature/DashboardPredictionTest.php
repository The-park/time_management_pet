<?php

test('dashboard predictor learns transition and time specific durations', function () {
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($source)
        ->toContain('transitionDurations')
        ->toContain('timeDurations')
        ->toContain('bumpNestedDuration(state.transitionDurations')
        ->toContain('bumpNestedDuration(state.timeDurations')
        ->toContain('const predictedDuration = (state, k, prevKey, bucket) =>');
});

test('dashboard predictor sizes generated rows from learned durations', function () {
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($source)
        ->toMatch('/const predictedMinutes = pick[\s\S]{0,180}predictedDuration\(state, pick\.key, prevKey, bucket\)/')
        ->toMatch('/const slotEnd = Math\.min\(cursor \+ predictedMinutes, endMin\)/')
        ->toContain('block${slots.length === 1 ?');
});
