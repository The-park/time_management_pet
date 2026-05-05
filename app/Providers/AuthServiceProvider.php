<?php

namespace App\Providers;

use App\Models\Countdown;
use App\Models\DailyGoal;
use App\Models\TimeBlock;
use App\Policies\CountdownPolicy;
use App\Policies\DailyGoalPolicy;
use App\Policies\TimeBlockPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        TimeBlock::class => TimeBlockPolicy::class,
        DailyGoal::class => DailyGoalPolicy::class,
        Countdown::class => CountdownPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
