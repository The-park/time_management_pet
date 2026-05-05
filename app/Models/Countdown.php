<?php

namespace App\Models;

use App\Models\Scopes\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Countdown extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'label',
        'started_at',
        'duration_seconds',
        'paused_at',
        'pause_accumulated_seconds',
        'state',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'duration_seconds' => 'integer',
            'pause_accumulated_seconds' => 'integer',
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
