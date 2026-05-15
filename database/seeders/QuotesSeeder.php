<?php

namespace Database\Seeders;

use App\Models\Quote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QuotesSeeder extends Seeder
{
    public function run(): void
    {
        // Keep the original 31 hand-picked entries. These were curated for
        // the bubble at launch and we want them to stay regardless of
        // whether the bulk corpus is imported. The bulk loader below
        // uses updateOrCreate keyed on text so dupes between the
        // hand-picked list and the corpus are safely deduped.
        $handPicked = [
            // ── badass ─────────────────────────────────────────────
            ['text' => 'Push yourself, because no one else is going to do it for you.', 'author' => null, 'source' => null, 'category' => 'badass'],
            ['text' => 'The pain you feel today will be the strength you feel tomorrow.', 'author' => null, 'source' => null, 'category' => 'badass'],
            ['text' => 'Discipline is choosing between what you want now and what you want most.', 'author' => 'Abraham Lincoln', 'source' => null, 'category' => 'badass'],
            ['text' => 'Hard work beats talent when talent doesn\'t work hard.', 'author' => 'Tim Notke', 'source' => null, 'category' => 'badass'],
            ['text' => 'Fall seven times, stand up eight.', 'author' => null, 'source' => 'Japanese proverb', 'category' => 'badass'],

            // ── productive ─────────────────────────────────────────
            ['text' => 'Don\'t watch the clock; do what it does. Keep going.', 'author' => 'Sam Levenson', 'source' => null, 'category' => 'productive'],
            ['text' => 'The way to get started is to quit talking and begin doing.', 'author' => 'Walt Disney', 'source' => null, 'category' => 'productive'],
            ['text' => 'Focus on being productive instead of busy.', 'author' => 'Tim Ferriss', 'source' => null, 'category' => 'productive'],
            ['text' => 'You don\'t have to be great to start, but you have to start to be great.', 'author' => 'Zig Ziglar', 'source' => null, 'category' => 'productive'],
            ['text' => 'Action is the foundational key to all success.', 'author' => 'Pablo Picasso', 'source' => null, 'category' => 'productive'],
            ['text' => 'Either you run the day or the day runs you.', 'author' => 'Jim Rohn', 'source' => null, 'category' => 'productive'],

            // ── aot ────────────────────────────────────────────────
            ['text' => 'If you don\'t fight, you can\'t win.', 'author' => 'Eren Yeager', 'source' => 'Attack on Titan', 'category' => 'aot'],
            ['text' => 'Tatakae. Fight.', 'author' => 'Eren Yeager', 'source' => 'Attack on Titan', 'category' => 'aot'],
            ['text' => 'The only thing we\'re allowed to do is to believe that we won\'t regret the choice we made.', 'author' => 'Levi Ackerman', 'source' => 'Attack on Titan', 'category' => 'aot'],
            ['text' => 'This world is cruel, and yet so beautiful.', 'author' => 'Mikasa Ackerman', 'source' => 'Attack on Titan', 'category' => 'aot'],
            ['text' => 'The world is merciless, and it\'s also very beautiful.', 'author' => 'Mikasa Ackerman', 'source' => 'Attack on Titan', 'category' => 'aot'],
            ['text' => 'If you win, you live. If you lose, you die. If you don\'t fight, you can\'t win.', 'author' => 'Eren Yeager', 'source' => 'Attack on Titan', 'category' => 'aot'],
            ['text' => 'Devote your hearts!', 'author' => 'Erwin Smith', 'source' => 'Attack on Titan', 'category' => 'aot'],

            // ── anime ──────────────────────────────────────────────
            ['text' => 'I never go back on my word — that\'s my nindo, my ninja way.', 'author' => 'Naruto Uzumaki', 'source' => 'Naruto', 'category' => 'anime'],
            ['text' => 'If you don\'t take risks, you can\'t create a future.', 'author' => 'Monkey D. Luffy', 'source' => 'One Piece', 'category' => 'anime'],
            ['text' => 'Throughout heaven and earth, I alone am the honored one.', 'author' => 'Satoru Gojo', 'source' => 'Jujutsu Kaisen', 'category' => 'anime'],
            ['text' => 'Set your heart ablaze.', 'author' => 'Kyojuro Rengoku', 'source' => 'Demon Slayer', 'category' => 'anime'],
            ['text' => 'Hard work is worthless for those that don\'t believe in themselves.', 'author' => 'Naruto Uzumaki', 'source' => 'Naruto', 'category' => 'anime'],
            ['text' => 'A lesson without pain is meaningless. You cannot gain something without sacrificing something else in return.', 'author' => 'Edward Elric', 'source' => 'Fullmetal Alchemist', 'category' => 'anime'],
            ['text' => 'Plus Ultra!', 'author' => 'All Might', 'source' => 'My Hero Academia', 'category' => 'anime'],

            // ── movie ──────────────────────────────────────────────
            ['text' => 'It ain\'t about how hard you hit. It\'s about how hard you can get hit and keep moving forward.', 'author' => 'Rocky Balboa', 'source' => 'Rocky', 'category' => 'movie'],
            ['text' => 'Don\'t ever let somebody tell you you can\'t do something.', 'author' => 'Chris Gardner', 'source' => 'The Pursuit of Happyness', 'category' => 'movie'],
            ['text' => 'What we do in life echoes in eternity.', 'author' => 'Maximus', 'source' => 'Gladiator', 'category' => 'movie'],
            ['text' => 'Why do we fall? So that we can learn to pick ourselves up.', 'author' => 'Alfred Pennyworth', 'source' => 'Batman Begins', 'category' => 'movie'],
            ['text' => 'Do, or do not. There is no try.', 'author' => 'Yoda', 'source' => 'Star Wars', 'category' => 'movie'],
            ['text' => 'Get busy living, or get busy dying.', 'author' => 'Andy Dufresne', 'source' => 'The Shawshank Redemption', 'category' => 'movie'],
        ];

        // Merge in the consolidated bulk corpus. Lives at
        // database/seeders/quotes_data/all.php as a single flat array of
        // ['text','author','source','category'] entries (~4.6k rows). The
        // per-category files this used to load were merged into one to cut
        // production disk footprint and inode count.
        $all = $handPicked;
        $corpusFile = __DIR__ . '/quotes_data/all.php';
        if (is_file($corpusFile)) {
            /** @var array<int, array<string, mixed>> $batch */
            $batch = require $corpusFile;
            if (is_array($batch)) {
                foreach ($batch as $row) {
                    $all[] = $row;
                }
            }
        }

        // Detect whether the schema has a user_id column. Another agent may
        // add it at any point — guard so we don't break either side. When
        // present we set NULL on every seeded row (admin pool / global).
        $hasUserId = Schema::hasColumn('quotes', 'user_id');

        // Process in chunks of 500 inside a single transaction per chunk
        // so we don't hold one massive transaction across all 5k rows
        // and risk hitting MySQL's max_allowed_packet / lock-wait limits.
        $chunks = array_chunk($all, 500);
        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk, $hasUserId): void {
                foreach ($chunk as $q) {
                    if (! isset($q['text']) || ! is_string($q['text'])) {
                        continue;
                    }
                    // Trim and clamp to the column's safe ceiling. Quote.text
                    // is VARCHAR(500); we cap at 280 so it fits any tooltip /
                    // bubble layout and to keep keys stable across the seeder
                    // and future imports.
                    $text = trim($q['text']);
                    if ($text === '') {
                        continue;
                    }
                    if (mb_strlen($text) > 280) {
                        // No ellipsis — clean word-boundary truncate.
                        $text = (string) Str::limit($text, 280, '');
                    }

                    $author = isset($q['author']) && is_string($q['author']) && $q['author'] !== ''
                        ? mb_substr($q['author'], 0, 120)
                        : null;
                    $source = isset($q['source']) && is_string($q['source']) && $q['source'] !== ''
                        ? mb_substr($q['source'], 0, 120)
                        : null;
                    $category = isset($q['category']) && in_array($q['category'], Quote::ALLOWED_CATEGORIES, true)
                        ? $q['category']
                        : 'other';

                    $attrs = [
                        'author' => $author,
                        'source' => $source,
                        'category' => $category,
                        'is_active' => true,
                    ];
                    if ($hasUserId) {
                        $attrs['user_id'] = null;
                    }

                    Quote::updateOrCreate(['text' => $text], $attrs);
                }
            });
        }
    }
}
