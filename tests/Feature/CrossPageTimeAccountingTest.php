<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminUserController;
use App\Models\Goal;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CrossPageTimeAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Time Tester',
            'email' => 'time+'.uniqid().'@example.test',
            'password' => bcrypt('password-strong'),
            'timezone' => 'UTC',
            'end_of_day_time' => '22:00',
            'wake_up_time' => '06:00',
            'gap_threshold_minutes' => 30,
            'status' => 'active',
        ]);
    }

    public function test_goal_breakdown_keeps_neutral_time_out_of_unlogged(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 22, 10, 0, 0, 'UTC'));
        $user = $this->user();
        $this->actingAs($user);

        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Focus project',
            'category' => 'custom',
            'start_date' => '2026-06-22',
            'target_date' => '2026-06-22',
            'original_target_date' => '2026-06-22',
            'keywords' => ['focus'],
            'status' => 'active',
        ]);

        TimeBlock::create([
            'user_id' => $user->id,
            'external_id' => 'neutral-focus',
            'start_time' => Carbon::create(2026, 6, 22, 7, 0, 0, 'UTC'),
            'end_time' => Carbon::create(2026, 6, 22, 8, 0, 0, 'UTC'),
            'duration_seconds' => 3600,
            'reason' => 'focus project planning',
            'category' => 'neutral',
            'auto_filled' => false,
        ]);

        $response = $this->get(route('goals.show', $goal));

        $response->assertOk();
        $breakdown = $response->viewData('activityBreakdown');
        $this->assertSame(0.0, $breakdown['productive_hours']);
        $this->assertSame(1.0, $breakdown['neutral_hours']);
        $this->assertSame(0.0, $breakdown['wasted_hours']);
        $this->assertSame(3.0, $breakdown['unlogged_awake_hours']);
    }

    public function test_admin_day_uses_elapsed_sleep_and_neutral_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 22, 6, 30, 0, 'UTC'));
        $user = $this->user();

        TimeBlock::create([
            'user_id' => $user->id,
            'external_id' => 'neutral-morning',
            'start_time' => Carbon::create(2026, 6, 22, 6, 0, 0, 'UTC'),
            'end_time' => Carbon::create(2026, 6, 22, 6, 30, 0, 'UTC'),
            'duration_seconds' => 1800,
            'reason' => 'breakfast',
            'category' => 'neutral',
            'auto_filled' => false,
        ]);

        $view = app(AdminUserController::class)->day(
            Request::create('/admin/users/'.$user->id.'/day/2026-06-22'),
            $user->id,
            '2026-06-22',
        );
        $data = $view->getData();

        $this->assertSame(6 * 3600, $data['sleepSec']);
        $this->assertSame(1800, $data['awakeSec']);
        $this->assertSame(1800, $data['neutralSec']);
        $this->assertSame(0, $data['unloggedSec']);
    }
}
