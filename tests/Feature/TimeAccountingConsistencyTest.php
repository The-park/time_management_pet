<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

test('current history day treats midnight through wake time as scheduled sleep', function () {
    Carbon::setTestNow('2026-06-22 06:30:00 UTC');
    $user = User::factory()->create([
        'timezone' => 'UTC',
        'wake_up_time' => '06:00',
        'end_of_day_time' => '22:00',
        'created_at' => '2026-06-01 00:00:00',
    ]);

    $this->actingAs($user)
        ->get('/history/day/2026-06-22')
        ->assertOk()
        ->assertViewHas('sleepMs', 6 * 60 * 60 * 1000)
        ->assertViewHas('awakeMs', 30 * 60 * 1000)
        ->assertViewHas('unloggedMs', 30 * 60 * 1000);
});

test('current history day stops unlogged time at configured end of day', function () {
    Carbon::setTestNow('2026-06-22 23:30:00 UTC');
    $user = User::factory()->create([
        'timezone' => 'UTC',
        'wake_up_time' => '06:00',
        'end_of_day_time' => '22:00',
        'created_at' => '2026-06-01 00:00:00',
    ]);

    $this->actingAs($user)
        ->get('/history/day/2026-06-22')
        ->assertOk()
        ->assertViewHas('awakeMs', 16 * 60 * 60 * 1000)
        ->assertViewHas('unloggedMs', 16 * 60 * 60 * 1000);
});

test('past history day renders with configured sleep window label', function () {
    Carbon::setTestNow('2026-06-25 12:00:00 UTC');
    $user = User::factory()->create([
        'timezone' => 'UTC',
        'wake_up_time' => '06:00',
        'end_of_day_time' => '22:00',
        'created_at' => '2026-06-01 00:00:00',
    ]);

    $this->actingAs($user)
        ->get('/history/day/2026-06-24')
        ->assertOk()
        ->assertViewHas('sleepLabel', fn ($label) => str_contains($label, '10:00 PM') && str_contains($label, '6:00 AM'))
        ->assertSee('Time breakdown');
});

test('dashboard period accounting uses overlapping sleep and excludes neutral from unlogged', function () {
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($source)
        ->toContain('const scheduledSleepMsInRange = (rangeStart, rangeEnd, wakeMins, endMins) =>')
        ->toContain('const unloggedAwakeMs = Math.max(0, awakeElapsedMs - productiveMs - wastedMs - neutralMs)')
        ->toContain('data-period-neutral')
        ->toContain('unloggedDayMs = max(0, $awakeDayMs - (($productiveSec + $wastedSec + $neutralSec) * 1000))');
});
