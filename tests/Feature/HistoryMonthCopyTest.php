<?php

test('history month view exposes copy month csv action', function () {
    $source = file_get_contents(resource_path('views/history/index.blade.php'));

    expect($source)
        ->toContain('data-copy-month-csv')
        ->toContain('Copy month')
        ->toContain("const header = ['Date', 'Start', 'End', 'Duration', 'Reason', 'Category']");
});

test('history month csv copies full completed months and current month through today', function () {
    $source = file_get_contents(resource_path('views/history/index.blade.php'));

    expect($source)
        ->toContain('const monthCopyEndDay = (year, month) =>')
        ->toContain('return now.getDate();')
        ->toContain('return daysInMonth(year, month);')
        ->toContain('No blocks logged');
});
