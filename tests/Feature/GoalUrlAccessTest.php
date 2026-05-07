<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Captures the user's real-world bug: visiting /goals/9 was "broken".
 * The root cause is that the BelongsToUser global scope on the Goal model
 * runs during route model binding (SubstituteBindings), which happens
 * BEFORE the route's auth middleware. So:
 *   - Logged-out user  → scope returns nothing → 404 (instead of login redirect)
 *   - Wrong-user      → scope returns nothing → 404 (no friendly message)
 *   - Owner           → works
 */
class GoalUrlAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'A',
            'email' => 'a+'.uniqid().'@x.com',
            'password' => bcrypt('password-strong'),
            'timezone' => 'UTC',
            'end_of_day_time' => '22:00',
            'wake_up_time' => '07:00',
            'gap_threshold_minutes' => 30,
            'status' => 'active',
        ]);
    }

    private function goalForUser(User $u): Goal
    {
        $this->actingAs($u);
        return Goal::create([
            'user_id' => $u->id,
            'title' => 'g',
            'category' => 'exam',
            'start_date' => now()->subDays(2)->toDateString(),
            'target_date' => now()->addDays(20)->toDateString(),
            'original_target_date' => now()->addDays(20)->toDateString(),
            'keywords' => ['focus'],
            'status' => 'active',
        ]);
    }

    public function test_owner_can_view_their_goal(): void
    {
        $u = $this->user();
        $g = $this->goalForUser($u);

        $this->actingAs($u)
            ->get('/goals/'.$g->id)
            ->assertOk();
    }

    public function test_logged_out_user_is_redirected_to_login_not_404(): void
    {
        $u = $this->user();
        $g = $this->goalForUser($u);

        // Fresh request, no auth.
        auth()->logout();
        $this->refreshApplication();

        $resp = $this->get('/goals/'.$g->id);
        $resp->assertRedirect(route('login'));
    }

    public function test_non_owner_sees_friendly_redirect_not_404(): void
    {
        $owner = $this->user();
        $g = $this->goalForUser($owner);

        $other = $this->user();
        // We expect: redirect to /goals (with toast), NOT a generic 404.
        $resp = $this->actingAs($other)->get('/goals/'.$g->id);
        $resp->assertRedirect(route('goals.index'));
    }

    public function test_visiting_nonexistent_goal_redirects_to_goals_index(): void
    {
        $u = $this->user();
        $resp = $this->actingAs($u)->get('/goals/99999');
        $resp->assertRedirect(route('goals.index'));
    }
}
