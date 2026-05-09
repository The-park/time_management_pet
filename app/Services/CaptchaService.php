<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Math + honeypot CAPTCHA. No third-party dependency.
 *
 * Why this design:
 *   - Self-hosted, works on cPanel-style shared hosting with no API keys.
 *   - The math challenge is generated server-side, kept in cache against a
 *     short-lived random token; the token is the only thing exposed to the
 *     form — the answer never touches the client.
 *   - A honeypot text field is rendered alongside the visible question.
 *     Real users don't see it (CSS-hidden + tabindex=-1 + autocomplete=off);
 *     scripted bots that auto-fill every input will trip it.
 *   - Tokens are single-use: verify() pulls the entry out of cache so a
 *     stolen token can't be replayed.
 */
class CaptchaService
{
    private const CACHE_PREFIX = 'captcha:';
    private const TTL_MINUTES = 15;

    public function challenge(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $useMul = random_int(0, 1) === 1;
        $op = $useMul ? '×' : '+';
        $answer = $useMul ? $a * $b : $a + $b;
        $token = (string) Str::uuid();

        Cache::put(self::CACHE_PREFIX.$token, $answer, now()->addMinutes(self::TTL_MINUTES));

        return [
            'token' => $token,
            'question' => "What is {$a} {$op} {$b}?",
        ];
    }

    /**
     * Single-use verify. Pulls the entry out of cache so the same token
     * can't be replayed. Returns true only when the answer matches AND
     * the honeypot field is empty.
     */
    public function verify(?string $token, ?string $answer, ?string $honeypot = null): bool
    {
        if (! empty($honeypot)) {
            // Bot. Don't even check the math.
            if ($token) {
                Cache::forget(self::CACHE_PREFIX.$token);
            }
            return false;
        }
        if (! $token || ! is_string($answer) || trim($answer) === '') {
            return false;
        }

        $expected = Cache::pull(self::CACHE_PREFIX.$token);
        if ($expected === null) return false;

        return (int) trim($answer) === (int) $expected;
    }
}
