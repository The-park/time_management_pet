<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\GoalTimeAnalysisService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTimeAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $endOfDay = '22:00', string $wakeUp = '07:00'): User
    {
        return User::create([
            'name' => 'A',
            'email' => 'a+'.uniqid().'@x.com',
            'password' => bcrypt('password-strong'),
            'timezone' => 'UTC',
            'end_of_day_time' => $endOfDay,
            'wake_up_time' => $wakeUp,
            'gap_threshold_minutes' => 30,
            'status' => 'active',
        ]);
    }

    private function goal(User $u, CarbonImmutable $start, CarbonImmutable $target): Goal
    {
        $this->actingAs($u);
        return Goal::create([
            'user_id' => $u->id,
            'title' => 'g',
            'category' => 'exam',
            'start_date' => $start->toDateString(),
            'target_date' => $target->toDateString(),
            'original_target_date' => $target->toDateString(),
            'keywords' => ['focus'],
            'status' => 'active',
        ]);
    }

    public function test_remaining_window_breaks_into_sleep_and_awake(): void
    {
        $u = $this->user('22:00', '07:00');                    // 9h sleep / night
        $now = CarbonImmutable::create(2026, 5, 6, 12, 0, 0);  // noon today
        $g = $this->goal($u, $now, $now->addDays(7));          // 1 week ahead

        $r = app(GoalTimeAnalysisService::class)->analyze($g, $u, $now);

        // 8 bedtimes (22:00 on May 6, 7, ..., 13) fall inside
        // [May 6 12:00, May 13 23:59:59] — target is end-of-day, so the
        // last night before the deadline counts.
        $this->assertEquals(8, $r['remaining']['nights']);
        $this->assertEquals(72.0, $r['remaining']['sleep_hours']);   // 8 × 9
        // Wall-clock: noon today → end-of-day(23:59:59) on day+7
        // = 7 days + 11h 59m 59s ≈ 179.99h
        $this->assertGreaterThan(179.0, $r['remaining']['total_hours']);
        $this->assertLessThan(181.0, $r['remaining']['total_hours']);
        // Awake = total − sleep
        $this->assertEqualsWithDelta(
            $r['remaining']['total_hours'] - $r['remaining']['sleep_hours'],
            $r['remaining']['awake_hours'],
            0.5,
        );
    }

    public function test_elapsed_block_attribution_feeds_logged_and_unlogged(): void
    {
        $u = $this->user('22:00', '07:00');
        $now = CarbonImmutable::create(2026, 5, 6, 12, 0, 0);
        // Goal started yesterday morning so we have ~30h elapsed (1 night sleep).
        $start = CarbonImmutable::create(2026, 5, 5, 8, 0, 0);
        $target = $now->addDays(20);

        $g = Goal::create([
            'user_id' => $u->id,
            'title' => 'g',
            'category' => 'exam',
            'start_date' => $start->toDateString(),
            'target_date' => $target->toDateString(),
            'original_target_date' => $target->toDateString(),
            'keywords' => ['focus'],
            'status' => 'active',
        ]);

        // Log 4h that match the keyword.
        TimeBlock::create([
            'user_id' => $u->id,
            'external_id' => 'b1',
            'start_time' => $start->copy()->addHours(2),
            'end_time' => $start->copy()->addHours(6),
            'duration_seconds' => 4 * 3600,
            'reason' => 'focus deep work',
            'auto_filled' => false,
        ]);

        $this->actingAs($u);
        $r = app(GoalTimeAnalysisService::class)->analyze($g, $u, $now);

        $this->assertEquals(4.0, $r['elapsed']['logged_hours']);
        $this->assertEquals(1, $r['elapsed']['nights']);            // one bedtime crossed
        $this->assertEquals(9.0, $r['elapsed']['sleep_hours']);
        // Unlogged awake = elapsed_awake − logged
        $this->assertEqualsWithDelta(
            $r['elapsed']['awake_hours'] - 4.0,
            $r['elapsed']['unlogged_awake_hours'],
            0.5,
        );
    }

    public function test_sleep_per_night_uses_user_settings(): void
    {
        $u = $this->user('23:00', '06:00');                    // 7h sleep
        $now = CarbonImmutable::create(2026, 5, 6, 12, 0, 0);
        $g = $this->goal($u, $now, $now->addDays(2));

        $r = app(GoalTimeAnalysisService::class)->analyze($g, $u, $now);

        $this->assertEquals(7.0, $r['sleep']['per_night_hours']);
        $this->assertEquals('11:00 PM', $r['sleep']['end_of_day']);
        $this->assertEquals('6:00 AM', $r['sleep']['wake_time']);
    }

    public function test_weeks_label_formats_long_windows_nicely(): void
    {
        $u = $this->user();
        $now = CarbonImmutable::create(2026, 5, 6, 0, 0, 0);
        $g = $this->goal($u, $now, $now->addDays(19));    // 2 weeks 5 days

        $r = app(GoalTimeAnalysisService::class)->analyze($g, $u, $now);

        $this->assertEquals('2 weeks 5 days', $r['remaining']['weeks_label']);
    }
}
