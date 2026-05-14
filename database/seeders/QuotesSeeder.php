<?php

namespace Database\Seeders;

use App\Models\Quote;
use Illuminate\Database\Seeder;

class QuotesSeeder extends Seeder
{
    public function run(): void
    {
        $quotes = [
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

        foreach ($quotes as $q) {
            Quote::updateOrCreate(
                ['text' => $q['text']],
                array_merge($q, ['is_active' => true])
            );
        }
    }
}
