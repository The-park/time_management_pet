<?php

namespace App\Models;

use App\Models\Scopes\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalLog extends Model
{
    use HasFactory;

    public const ACTION_CREATED = 'created';
    public const ACTION_EDITED = 'edited';
    public const ACTION_EXTENDED = 'extended';
    public const ACTION_SHORTENED = 'shortened';
    public const ACTION_COMPLETED = 'completed';
    public const ACTION_ABANDONED = 'abandoned';
    public const ACTION_REOPENED = 'reopened';

    protected $fillable = [
        'goal_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'reason' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToUser());
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_EDITED => 'Edited',
            self::ACTION_EXTENDED => 'Extended deadline',
            self::ACTION_SHORTENED => 'Shortened deadline',
            self::ACTION_COMPLETED => 'Completed',
            self::ACTION_ABANDONED => 'Abandoned',
            self::ACTION_REOPENED => 'Reopened',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
