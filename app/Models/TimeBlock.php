<?php

namespace App\Models;

use App\Models\Scopes\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeBlock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
        'duration_seconds',
        'reason',
        'auto_filled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_seconds' => 'integer',
            'auto_filled' => 'boolean',
            'reason' => 'encrypted',
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
