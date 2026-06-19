<?php

test('dashboard rules preview renders all active rules without the old five item cap', function () {
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($source)
        ->toContain('$dashboardRules = \App\Models\Rule::query()')
        ->toContain('->active()')
        ->not->toContain('->limit(5)')
        ->toContain('{{ $dashboardRules->count() }} active');
});

test('dashboard block start defaults to latest completed block or wake time', function () {
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($source)
        ->toContain('const latestCompletedEndMinutesTodayForForm = () =>')
        ->toContain("window.ChronoDashboardConfig?.wakeTime || '07:00'")
        ->toContain('const startMin = lastEnd !== null')
        ->toContain('? lastEnd');
});
