<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;

    public const ALLOWED_CATEGORIES = [
        'productive',
        'study',
        'focus',
        'self_improvement',
        'hard_truth',
        'discipline',
        'badass',
        'anime',
        'aot',
        'bleach',
        'vinland_saga',
        'misfits',
        'movie',
        'other',
    ];

    /**
     * Human-readable labels for each category slug. Falls back to a
     * Title Case version of the slug if the entry is missing.
     */
    public const CATEGORY_LABELS = [
        'productive' => 'Productive',
        'study' => 'Study',
        'focus' => 'Focus',
        'self_improvement' => 'Self-improvement',
        'hard_truth' => 'Hard truths',
        'discipline' => 'Discipline',
        'badass' => 'Badass',
        'anime' => 'Anime (general)',
        'aot' => 'Attack on Titan',
        'bleach' => 'Bleach',
        'vinland_saga' => 'Vinland Saga',
        'misfits' => 'Misfit heroes',
        'movie' => 'Movie',
        'other' => 'Other',
    ];

    public static function categoryLabel(string $slug): string
    {
        return self::CATEGORY_LABELS[$slug]
            ?? ucwords(str_replace('_', ' ', $slug));
    }

    public const SOURCES = ['admin', 'mine', 'mixed'];

    protected $fillable = [
        'user_id',
        'text',
        'author',
        'source',
        'category',
        'is_active',
    ];

    protected $casts = [
        // Past-rounds lesson (see GoalPolicy notes): PDO can stringify
        // integer FKs depending on driver, which breaks `$user->id === $quote->user_id`
        // strict comparison. Cast to int up front.
        'user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRandom(Builder $query): Builder
    {
        return $query->inRandomOrder();
    }

    /**
     * Filter the quote pool based on the user's source preference.
     *
     *  - admin: only global/curated quotes (user_id IS NULL)
     *  - mine:  only this user's own quotes
     *  - mixed: union of admin pool + this user's own
     */
    public function scopeForFeed(Builder $query, ?int $userId, string $source): Builder
    {
        $source = in_array($source, self::SOURCES, true) ? $source : 'mixed';

        return match ($source) {
            'admin' => $query->whereNull('user_id'),
            'mine'  => $query->where('user_id', $userId),
            default => $query->where(function (Builder $q) use ($userId) {
                $q->whereNull('user_id');
                if ($userId !== null) {
                    $q->orWhere('user_id', $userId);
                }
            }),
        };
    }
}
