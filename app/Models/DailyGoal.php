<?php

namespace App\Models;

use App\Models\Scopes\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyGoal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'goal_text',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'goal_text' => 'encrypted',
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
}
