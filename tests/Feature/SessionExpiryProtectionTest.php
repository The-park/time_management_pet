<?php

test('time block sync rejects a guest json request', function () {
    $this->postJson('/time-blocks/sync', ['blocks' => []])->assertUnauthorized();
});

test('layout and time block sync react to expired sessions', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $dashboard = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($layout)
        ->toContain('sessionLifetimeMs: @json(max(1, (int) config(\'session.lifetime\', 120)) * 60 * 1000)')
        ->toContain("const SESSION_END_KEY = 'chrono.sessionEnded.v1'")
        ->toContain('const scheduleIdleLogout = () =>')
        ->toContain('window.ChronoAuthSessionExpired = endExpiredSession')
        ->toContain('window.ChronoClearUserLocalData?.()')
        ->toContain('data-chrono-logout');

    expect($dashboard)
        ->toContain('if (response.status === 401 || response.status === 419)')
        ->toContain('window.ChronoAuthSessionExpired?.();');
});
