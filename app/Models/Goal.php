<?php

namespace App\Models;

use App\Models\Scopes\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'start_date',
        'target_date',
        'original_target_date',
        'keywords',
        'status',
        'completed_at',
        'extension_count',
        'change_count',
        'last_probability',
        'last_probability_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_date' => 'date',
            'original_target_date' => 'date',
            'completed_at' => 'datetime',
            'last_probability_at' => 'datetime',
            'last_probability' => 'decimal:2',
            'extension_count' => 'integer',
            'change_count' => 'integer',
            'description' => 'encrypted',
            'keywords' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToUser());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GoalLog::class)->latest();
    }

    /**
     * Time blocks logged within this goal's date window.
     * The goal pulls signal from the user's regular hourly logs — no
     * separate progress model. Every productive hour the user logs on
     * the dashboard contributes to every active goal whose window covers
     * that day.
     */
    public function timeBlocks()
    {
        return TimeBlock::query()
            ->where('user_id', $this->user_id)
            ->whereBetween('start_time', [
                CarbonImmutable::parse($this->start_date)->startOfDay(),
                CarbonImmutable::parse($this->target_date)->endOfDay(),
            ]);
    }
}
