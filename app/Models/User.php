<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'end_of_day_time',
        'wake_up_time',
        'gap_threshold_minutes',
        'status',
        'backup_email_enabled',
        'backup_email_address',
        'backup_auto_daily',
        'backup_last_sent_at',
        'backup_count',
        'flying_quotes_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'backup_email_enabled' => 'boolean',
            'backup_auto_daily' => 'boolean',
            'backup_last_sent_at' => 'datetime',
            'backup_count' => 'integer',
            'flying_quotes_enabled' => 'boolean',
        ];
    }

    public function dataExportLogs()
    {
        return $this->hasMany(\App\Models\DataExportLog::class);
    }

    public function timeBlocks()
    {
        return $this->hasMany(TimeBlock::class);
    }

    public function dailyGoals()
    {
        return $this->hasMany(DailyGoal::class);
    }

    public function countdowns()
    {
        return $this->hasMany(Countdown::class);
    }

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }
}
