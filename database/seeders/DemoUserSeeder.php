<?php

namespace Database\Seeders;

use App\Models\DailyGoal;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@chronolog.test'],
            [
                'name' => 'Time Management Pet · Demo',
                'password' => 'password1234',
                'timezone' => 'UTC',
                'end_of_day_time' => '22:00',
                'wake_up_time' => '07:00',
                'gap_threshold_minutes' => 60,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $startDay = Carbon::now($user->timezone)->startOfDay()->subDays(6);
        $reasons = [
            'Deep work sprint',
            'Client review',
            'Engineering sync',
            'Build and test cycle',
        ];

        for ($i = 0; $i < 7; $i++) {
            $day = $startDay->copy()->addDays($i);

            DailyGoal::updateOrCreate(
                ['user_id' => $user->id, 'date' => $day->toDateString()],
                ['goal_text' => 'Focus on the main milestone and close loops early.']
            );

            $blocks = [
                [$day->copy()->setTime(9, 0), $day->copy()->setTime(11, 30), $reasons[$i % 4], false],
                [$day->copy()->setTime(12, 30), $day->copy()->setTime(15, 0), 'Project work block', false],
                [$day->copy()->setTime(15, 30), $day->copy()->setTime(17, 45), 'Admin + follow-ups', $i === 2],
            ];

            foreach ($blocks as [$start, $end, $reason, $autoFilled]) {
                $startUtc = $start->copy()->utc();
                $endUtc = $end->copy()->utc();

                TimeBlock::create([
                    'user_id' => $user->id,
                    'start_time' => $startUtc,
                    'end_time' => $endUtc,
                    'duration_seconds' => $endUtc->diffInSeconds($startUtc),
                    'reason' => $reason,
                    'auto_filled' => $autoFilled,
                ]);
            }
        }
    }
}
