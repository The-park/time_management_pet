<?php

test('app header stays visible while scrolling', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($layout)
        ->toContain('<header class="sticky top-0 z-40')
        ->toContain('backdrop-blur-xl')
        ->toContain('flex flex-wrap items-center justify-between')
        ->toContain('flex flex-wrap items-center justify-end');
});

test('last log reminder includes end of day countdown and urgency tones', function () {
    $partial = file_get_contents(resource_path('views/partials/flying-quote.blade.php'));

    expect($partial)
        ->toContain('const END_OF_DAY_TIME = @json($endOfDayTime)')
        ->toContain('const formatClock = (ms) =>')
        ->toContain('const gapTone = (ms) =>')
        ->toContain('const dayTone = (ms) =>')
        ->toContain('timerEl.dataset.gapTone = latest ? gapTone(elapsedMs) : \'watch\'')
        ->toContain('timerEl.dataset.dayTone = dayEnded ? \'danger\' : dayTone(remainingMs)')
        ->toContain('End of day left')
        ->toContain('1h amber, 2h orange, 3h red')
        ->toContain('setInterval(refreshLastLogTimer, 1000)');
});
