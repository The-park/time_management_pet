<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\GoalAttributionService;
use App\Services\GoalKeywordExtractor;
use App\Services\GoalProbabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Tester',
            'email' => 'tester+'.uniqid().'@example.com',
            'password' => bcrypt('secret-password'),
            'timezone' => 'UTC',
            'end_of_day_time' => '22:00',
            'wake_up_time' => '07:00',
            'gap_threshold_minutes' => 30,
            'status' => 'active',
        ]);
    }

    private function goal(User $user, string $title, array $keywords, int $startDaysAgo = 5, int $targetDaysAhead = 25): Goal
    {
        $this->actingAs($user);
        return Goal::create([
            'user_id' => $user->id,
            'title' => $title,
            'category' => 'exam',
            'start_date' => now()->subDays($startDaysAgo)->toDateString(),
            'target_date' => now()->addDays($targetDaysAhead)->toDateString(),
            'original_target_date' => now()->addDays($targetDaysAhead)->toDateString(),
            'keywords' => $keywords,
            'status' => 'active',
        ]);
    }

    private function block(User $user, string $reason, int $daysAgo, int $hours = 1, string $extId = ''): TimeBlock
    {
        $start = now()->subDays($daysAgo)->setTime(10, 0);
        return TimeBlock::create([
            'user_id' => $user->id,
            'external_id' => $extId !== '' ? $extId : 'b'.uniqid(),
            'start_time' => $start,
            'end_time' => $start->copy()->addHours($hours),
            'duration_seconds' => $hours * 3600,
            'reason' => $reason,
            'auto_filled' => false,
        ]);
    }

    public function test_keyword_extractor_drops_stopwords_and_keeps_signal(): void
    {
        $kw = (new GoalKeywordExtractor())->extract('Complete AWS Certified Solutions Architect Exam');
        $this->assertContains('aws', $kw);
        $this->assertContains('solutions', $kw);
        $this->assertContains('architect', $kw);
        $this->assertNotContains('complete', $kw);
        $this->assertNotContains('certified', $kw);
        $this->assertNotContains('exam', $kw);
    }

    public function test_block_with_matching_keyword_is_fully_attributed(): void
    {
        $u = $this->user();
        $aws = $this->goal($u, 'AWS Solutions Architect', ['aws', 'ec2']);
        $this->block($u, 'AWS IAM hands-on lab', 1, 2);

        $attr = app(GoalAttributionService::class)->forGoal($aws);

        $this->assertEquals(2.0, $attr['hours_done']);
        $this->assertEquals(1, $attr['days_with_logs']);
        $this->assertCount(1, $attr['blocks']);
    }

    public function test_block_with_no_matching_keyword_is_unattributed(): void
    {
        $u = $this->user();
        $aws = $this->goal($u, 'AWS', ['aws']);
        $this->block($u, 'random meeting notes', 1, 2);

        $attr = app(GoalAttributionService::class)->forGoal($aws);

        $this->assertEquals(0.0, $attr['hours_done']);
        $this->assertEquals(0, $attr['days_with_logs']);
        $this->assertCount(0, $attr['blocks']);
    }

    public function test_block_matching_two_goals_is_split_proportionally(): void
    {
        $u = $this->user();
        $aws = $this->goal($u, 'AWS', ['aws', 'vpc']);
        $ceh = $this->goal($u, 'CEH', ['ceh', 'pentest']);
        // both keyword sets get a whole-word hit → 50/50 split
        $this->block($u, 'aws vpc + pentest tools', 1, 2);

        $awsAttr = app(GoalAttributionService::class)->forGoal($aws);
        $cehAttr = app(GoalAttributionService::class)->forGoal($ceh);

        $this->assertEquals(1.0, $awsAttr['hours_done']);
        $this->assertEquals(1.0, $cehAttr['hours_done']);
    }

    public function test_blocks_outside_window_are_not_credited(): void
    {
        $u = $this->user();
        $g = $this->goal($u, 'Goal', ['focus'], startDaysAgo: 3, targetDaysAhead: 30);
        $this->block($u, 'focus session', 10);   // before window
        $this->block($u, 'focus session today', 1);

        $attr = app(GoalAttributionService::class)->forGoal($g);

        $this->assertEquals(1.0, $attr['hours_done']);
        $this->assertEquals(1, $attr['days_with_logs']);
    }

    public function test_negative_duration_blocks_are_excluded(): void
    {
        $u = $this->user();
        $g = $this->goal($u, 'Study', ['study']);
        // wasted-time encoding (negative duration)
        TimeBlock::create([
            'user_id' => $u->id,
            'external_id' => 'wasted_1',
            'start_time' => now()->subDays(1)->setTime(10, 0),
            'end_time' => now()->subDays(1)->setTime(11, 0),
            'duration_seconds' => -3600,
            'reason' => 'study session',
            'auto_filled' => true,
        ]);

        $attr = app(GoalAttributionService::class)->forGoal($g);

        $this->assertEquals(0.0, $attr['hours_done']);
    }

    public function test_goal_created_today_with_logs_today_credits_hours(): void
    {
        // Reproduces tester1's bug: goal "ggg" created today with start_date=today,
        // user logs 4.25h today, but the dashboard summary showed 0h logged.
        // Root cause: probability service short-circuited on days_passed===0
        // before running attribution.
        $u = $this->user();
        $g = $this->goal($u, 'ggg', ['ggg'], startDaysAgo: 0, targetDaysAhead: 19);

        $this->block($u, 'read ggg', 0, 1, 'b1');           // today
        $this->block($u, 'read for ggg', 0, 1, 'b2');       // today
        $this->block($u, 'ggg read and written notes', 0, 2, 'b3');   // today, 2h

        $svc = app(GoalProbabilityService::class);
        $g->refresh();
        $r = $svc->compute($g);

        $this->assertEquals(4.0, $r['details']['hours_done'],
            'Hours logged today must be credited even though days_passed=0');
        $this->assertEquals(1, $r['details']['days_with_logs']);
        $this->assertGreaterThan(50.0, $r['percent'],
            'With 4h of attributed work today, probability should beat the 50% baseline.');
    }

    public function test_brand_new_goal_with_no_logs_returns_neutral_baseline(): void
    {
        $u = $this->user();
        $g = $this->goal($u, 'Empty', ['empty'], startDaysAgo: 0, targetDaysAhead: 30);

        $r = app(GoalProbabilityService::class)->compute($g);

        $this->assertEquals(50.0, $r['percent']);
        $this->assertEquals(0, $r['details']['hours_done']);
    }

    public function test_probability_rises_with_attributed_hours(): void
    {
        $u = $this->user();
        $g = $this->goal($u, 'Goal', ['focus'], startDaysAgo: 7, targetDaysAhead: 20);

        $svc = app(GoalProbabilityService::class);
        $empty = $svc->compute($g);

        for ($i = 1; $i <= 6; $i++) {
            $this->block($u, 'focus deep work', $i, 2, "b$i");
        }
        // Force a fresh goal instance so cached attributes don't bleed.
        $g->refresh();
        $loaded = $svc->compute($g);

        $this->assertGreaterThan($empty['percent'], $loaded['percent']);
        $this->assertEquals(12.0, $loaded['details']['hours_done']);
    }

    public function test_sync_endpoint_persists_localStorage_snapshot(): void
    {
        $u = $this->user();

        $payload = [
            'blocks' => [
                ['id' => 'cli_1', 'date' => now()->subDays(1)->toDateString(), 'start' => '10:00', 'end' => '11:30', 'durationMs' => 5400000, 'label' => 'study aws', 'auto_filled' => false],
                ['id' => 'cli_2', 'date' => now()->subDays(2)->toDateString(), 'start' => '14:00', 'end' => '15:00', 'durationMs' => 3600000, 'label' => 'random meeting', 'auto_filled' => false],
            ],
        ];

        $this->actingAs($u)
            ->postJson('/time-blocks/sync', $payload)
            ->assertOk()
            ->assertJsonFragment(['ok' => true, 'count' => 2]);

        $this->assertDatabaseCount('time_blocks', 2);
        $this->assertDatabaseHas('time_blocks', [
            'user_id' => $u->id,
            'external_id' => 'cli_1',
            'duration_seconds' => 5400,
        ]);
    }

    public function test_snapshot_endpoint_returns_users_blocks_in_localstorage_shape(): void
    {
        $u = $this->user();
        $start = now()->subDay()->setTime(10, 0);
        TimeBlock::create([
            'user_id' => $u->id,
            'external_id' => 'srv_b1',
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'duration_seconds' => 7200,
            'reason' => 'aws iam review',
            'auto_filled' => false,
        ]);

        $resp = $this->actingAs($u)
            ->getJson('/time-blocks/snapshot')
            ->assertOk();

        $data = $resp->json();
        $this->assertTrue($data['ok']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals('srv_b1', $data['blocks'][0]['id']);
        $this->assertEquals($start->toDateString(), $data['blocks'][0]['date']);
        $this->assertEquals('10:00', $data['blocks'][0]['start']);
        $this->assertEquals('12:00', $data['blocks'][0]['end']);
        $this->assertEquals(7200000, $data['blocks'][0]['durationMs']);
        $this->assertEquals('aws iam review', $data['blocks'][0]['label']);
    }

    public function test_snapshot_round_trip_preserves_data_after_logout_login(): void
    {
        // Reproduces the user's report: yesterday's blocks survive a logout
        // and a fresh login as long as we hydrate from the server before any
        // destructive sync fires.
        $u = $this->user();

        $this->actingAs($u)->postJson('/time-blocks/sync', [
            'blocks' => [
                ['id' => 'b1', 'date' => now()->subDay()->toDateString(), 'start' => '10:00', 'end' => '11:00', 'durationMs' => 3600000, 'label' => 'aws review'],
                ['id' => 'b2', 'date' => now()->subDay()->toDateString(), 'start' => '14:00', 'end' => '15:00', 'durationMs' => 3600000, 'label' => 'meeting'],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('time_blocks', 2);

        // User "logs out / opens fresh browser" → server snapshot should
        // still return both blocks. (The dashboard would hydrate localStorage
        // from this before any push fires.)
        auth()->logout();
        $this->assertGuest();

        $resp = $this->actingAs($u->fresh())
            ->getJson('/time-blocks/snapshot')
            ->assertOk();
        $this->assertEquals(2, $resp->json('count'));

        // CRITICAL: confirm the destructive sync only fires AFTER the client
        // has the snapshot. If a client posts an empty snapshot (e.g. fresh
        // browser, no localStorage) without first hydrating, the DB would be
        // wiped — that's the bug. We don't simulate the full JS flow here,
        // but the snapshot endpoint exists for the JS to call first.
        $this->assertDatabaseCount('time_blocks', 2);
    }

    public function test_sync_replaces_full_snapshot(): void
    {
        $u = $this->user();
        $this->block($u, 'old block', 1, 1, 'kept');

        $this->assertDatabaseCount('time_blocks', 1);

        $this->actingAs($u)->postJson('/time-blocks/sync', [
            'blocks' => [
                ['id' => 'new_1', 'date' => now()->toDateString(), 'start' => '09:00', 'end' => '10:00', 'durationMs' => 3600000, 'label' => 'new block'],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('time_blocks', ['external_id' => 'kept']);
        $this->assertDatabaseHas('time_blocks', ['external_id' => 'new_1']);
    }

    public function test_add_keyword_endpoint_appends_and_logs(): void
    {
        $u = $this->user();
        $g = $this->goal($u, 'AWS', ['aws']);

        $this->actingAs($u)
            ->post(route('goals.keywords.add', $g), ['keyword' => 'EC2'])
            ->assertRedirect(route('goals.show', $g));

        $g->refresh();
        $this->assertContains('ec2', $g->keywords);
        $this->assertEquals(1, $g->logs()->count());
    }

    public function test_extending_goal_increments_extension_count_and_logs_reason(): void
    {
        $u = $this->user();
        $g = $this->goal($u, 'Test', ['test']);

        $newTarget = $g->target_date->copy()->addDays(14)->toDateString();

        $this->actingAs($u)
            ->post(route('goals.extend', $g), [
                'target_date' => $newTarget,
                'reason' => 'Scope grew',
            ])
            ->assertRedirect(route('goals.show', $g));

        $g->refresh();
        $this->assertEquals(1, $g->extension_count);
        $this->assertEquals(1, $g->change_count);
        $this->assertEquals($newTarget, $g->target_date->toDateString());

        $log = $g->logs()->first();
        $this->assertEquals('extended', $log->action);
        $this->assertEquals('Scope grew', $log->reason);
    }
}
