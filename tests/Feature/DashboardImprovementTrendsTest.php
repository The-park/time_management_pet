<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard shows completed-period improvement trends', function () {
    $user = User::factory()->create([
        'timezone' => 'UTC',
        'end_of_day_time' => '22:00',
        'wake_up_time' => '06:00',
        'gap_threshold_minutes' => 30,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Improvement trends')
        ->assertSee('Day by day')
        ->assertSee('Week by week')
        ->assertSee('Month by month')
        ->assertSee('Completed periods only');
});

test('dashboard trend calculation uses scheduled sleep and excludes neutral from unlogged', function () {
    $source = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($source)
        ->toContain('$improvementTrendData')
        ->toContain('$makeTrendEntry')
        ->toContain('$scheduledSleepOverlapMs($effectiveStart, $rangeEnd)')
        ->toContain('$unloggedMs = max(0, $awakeMs - $productiveMs - $wastedMs - $neutralMs)')
        ->toContain('const renderImprovementTrends = (blocks, now) =>')
        ->toContain('renderImprovementTrends(blocks, now)');
});
