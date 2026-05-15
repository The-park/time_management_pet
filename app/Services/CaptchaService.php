<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Hardened math + honeypot CAPTCHA. No third-party dependency.
 *
 * Why this design (v2):
 *   - Self-hosted, works on cPanel-style shared hosting with no API keys.
 *   - The math challenge is generated server-side and stored in cache against
 *     a short-lived random token. The challenge question is NEVER rendered
 *     as plain text in the HTML — the partial points at /captcha/img/{token}.svg
 *     which streams an obfuscated SVG containing jittered/rotated digits,
 *     wavy distortion paths, and noise dots. HTML scrapers see only an
 *     <img> tag; to read the question they'd have to OCR the SVG.
 *   - A small proof-of-work nonce (SHA-256 with 4 leading hex zeros, ~16k
 *     average tries / ~80ms on a modern phone) must accompany the answer.
 *     Cheap for one human, painful for a scraper farming millions per minute.
 *   - A "submitted too quickly" timing gate (>=800ms after challenge issue)
 *     trips bots that instant-submit and per-IP rate-limiting (30/min) caps
 *     the brute force window if someone tries to spray attempts.
 *   - Honeypot text field stays — bots that auto-fill every input still trip.
 *   - Tokens are single-use: verify() pulls the entry out of cache so a
 *     stolen token can't be replayed.
 */
class CaptchaService
{
    private const CACHE_PREFIX = 'captcha:';
    private const TTL_MINUTES = 15;

    /** Minimum milliseconds between issuing a challenge and accepting an answer. */
    private const MIN_SUBMIT_MS = 800;

    /** Default proof-of-work difficulty (number of leading hex zeros in the digest). */
    private const POW_DIFFICULTY = 4;

    /** Per-IP rate limiter bucket for verify() attempts. */
    private const VERIFY_LIMIT_KEY = 'captcha-verify';
    private const VERIFY_MAX_ATTEMPTS = 30;
    private const VERIFY_DECAY_SECONDS = 60;

    /**
     * Generate a fresh challenge and stash everything needed to verify it.
     * The returned shape intentionally omits the question literal — the
     * partial uses `image_url` to render an SVG instead.
     *
     * @return array{token:string,image_url:string,pow_salt:string,pow_difficulty:int}
     */
    public function challenge(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $useMul = random_int(0, 1) === 1;
        $op = $useMul ? '×' : '+';
        $answer = $useMul ? $a * $b : $a + $b;

        $token = (string) Str::uuid();
        $powSalt = bin2hex(random_bytes(8));
        $createdAtMs = (int) round(microtime(true) * 1000);

        Cache::put(self::CACHE_PREFIX.$token, [
            'answer' => $answer,
            // The literal question text is needed only for the SVG renderer.
            // Stays out of HTML — only the SVG endpoint reads it back.
            'question' => "What is {$a} {$op} {$b}?",
            'pow_salt' => $powSalt,
            'pow_difficulty' => self::POW_DIFFICULTY,
            'created_at_ms' => $createdAtMs,
        ], now()->addMinutes(self::TTL_MINUTES));

        return [
            'token' => $token,
            'image_url' => route('captcha.image', ['token' => $token]),
            'pow_salt' => $powSalt,
            'pow_difficulty' => self::POW_DIFFICULTY,
        ];
    }

    /**
     * Render the cached question as a noisy SVG image. Returns a 404 if the
     * token is unknown or expired. The image deliberately uses jittered
     * positions, rotation, varying fill colours, wavy distortion paths, and
     * noise dots so a naive OCR pass has work to do. No inline JS.
     */
    public function renderSvg(string $token): Response
    {
        $entry = Cache::get(self::CACHE_PREFIX.$token);
        if (! is_array($entry) || ! isset($entry['question'])) {
            return response('Not found', 404);
        }

        $svg = $this->buildSvg((string) $entry['question']);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Build the SVG bytes for a given question literal. Pure function; no I/O.
     */
    private function buildSvg(string $question): string
    {
        $width = 210;
        $height = 56;
        // Slate palette — readable on both dark and light backgrounds.
        $palette = ['#0f172a', '#1e293b', '#334155', '#475569', '#3b0764', '#1e3a8a'];

        $chars = preg_split('//u', $question, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $charCount = max(1, count($chars));

        // Centre the run of glyphs horizontally with light padding.
        $padX = 14;
        $usableWidth = $width - 2 * $padX;
        $step = $usableWidth / $charCount;
        $baselineY = $height / 2 + 6;

        $parts = [];
        $parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $parts[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="Math challenge image">',
            $width,
            $height,
            $width,
            $height
        );
        // Light background tile so the image is readable on any container.
        $parts[] = sprintf('<rect width="%d" height="%d" fill="#e2e8f0" rx="6" ry="6"/>', $width, $height);

        // Noise dots first (under the text) so the glyphs remain readable.
        $dotCount = random_int(10, 20);
        for ($i = 0; $i < $dotCount; $i++) {
            $cx = random_int(2, $width - 2);
            $cy = random_int(2, $height - 2);
            $r = random_int(1, 2);
            $colour = $palette[array_rand($palette)];
            $opacity = random_int(20, 55) / 100;
            $parts[] = sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="%s" fill-opacity="%.2f"/>',
                $cx,
                $cy,
                $r,
                $colour,
                $opacity
            );
        }

        // Glyphs: each character jittered, rotated, with a per-char fill.
        foreach ($chars as $i => $ch) {
            $cx = (int) round($padX + $step * $i + $step / 2 + random_int(-2, 2));
            $cy = (int) round($baselineY + random_int(-3, 3));
            $rot = random_int(-8, 8);
            $colour = $palette[array_rand($palette)];
            $escaped = htmlspecialchars($ch, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $parts[] = sprintf(
                '<text x="%d" y="%d" font-family="Georgia, serif" font-size="22" font-weight="700" fill="%s" text-anchor="middle" transform="rotate(%d %d %d)">%s</text>',
                $cx,
                $cy,
                $colour,
                $rot,
                $cx,
                $cy,
                $escaped
            );
        }

        // 2–4 wavy distortion paths crossing the image so OCR has to denoise.
        $waveCount = random_int(2, 4);
        for ($w = 0; $w < $waveCount; $w++) {
            $yStart = random_int(8, $height - 8);
            $segments = 4;
            $segWidth = $width / $segments;
            $d = sprintf('M0 %d', $yStart);
            for ($s = 1; $s <= $segments; $s++) {
                $cx1 = (int) round($segWidth * ($s - 0.5));
                $cy1 = random_int(2, $height - 2);
                $x = (int) round($segWidth * $s);
                $y = random_int(8, $height - 8);
                $d .= sprintf(' Q%d %d %d %d', $cx1, $cy1, $x, $y);
            }
            $colour = $palette[array_rand($palette)];
            $opacity = random_int(35, 70) / 100;
            $parts[] = sprintf(
                '<path d="%s" stroke="%s" stroke-width="1.5" fill="none" stroke-opacity="%.2f" stroke-linecap="round"/>',
                $d,
                $colour,
                $opacity
            );
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    /**
     * Single-use verify. Pulls the entry out of cache so the same token
     * can't be replayed. Now also enforces:
     *   - honeypot empty (legacy)
     *   - answer matches
     *   - proof-of-work nonce produces SHA-256(salt:nonce) with the required
     *     number of leading hex zeros
     *   - submission timing >= MIN_SUBMIT_MS
     *   - per-IP rate limit on verify attempts
     *
     * @param string|null $ip Caller IP for per-IP throttling. Pass null to
     *                         skip the rate-limiter (e.g. in unit tests).
     */
    public function verify(
        ?string $token,
        ?string $answer,
        ?string $honeypot = null,
        ?string $powNonce = null,
        ?string $ip = null,
    ): bool {
        // Per-IP rate limiter on verify attempts. We hit it BEFORE pulling
        // the cache so a flood from one source can't burn through TTLs.
        if ($ip !== null) {
            $limiterKey = self::VERIFY_LIMIT_KEY.'|'.$ip;
            if (RateLimiter::tooManyAttempts($limiterKey, self::VERIFY_MAX_ATTEMPTS)) {
                return false;
            }
            RateLimiter::hit($limiterKey, self::VERIFY_DECAY_SECONDS);
        }

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

        $entry = Cache::pull(self::CACHE_PREFIX.$token);
        if (! is_array($entry)) {
            return false;
        }

        // Answer check.
        if (! isset($entry['answer']) || (int) trim($answer) !== (int) $entry['answer']) {
            return false;
        }

        // Timing: too fast = bot.
        $createdAtMs = (int) ($entry['created_at_ms'] ?? 0);
        $nowMs = (int) round(microtime(true) * 1000);
        if ($createdAtMs <= 0 || ($nowMs - $createdAtMs) < self::MIN_SUBMIT_MS) {
            return false;
        }

        // Proof-of-work.
        $salt = (string) ($entry['pow_salt'] ?? '');
        $difficulty = (int) ($entry['pow_difficulty'] ?? 0);
        if ($salt === '' || $difficulty <= 0) {
            return false;
        }
        if (! is_string($powNonce) || $powNonce === '') {
            return false;
        }
        // Cap nonce length so a malicious client can't force us to hash a
        // megabyte. The browser produces a short decimal — anything longer
        // than 32 chars is bogus.
        if (strlen($powNonce) > 32) {
            return false;
        }
        $digest = hash('sha256', $salt.':'.$powNonce);
        $needed = str_repeat('0', $difficulty);
        if (! str_starts_with($digest, $needed)) {
            return false;
        }

        return true;
    }

    /**
     * Failure-reason helper for the validation rule to surface a more
     * specific message. Returns one of:
     *   'ok'           — verified
     *   'rate'         — per-IP rate limit exceeded
     *   'honeypot'     — honeypot tripped
     *   'token'        — missing / expired / wrong-shape entry
     *   'answer'       — wrong number
     *   'timing'       — submitted too fast
     *   'pow'          — missing / invalid proof-of-work nonce
     *
     * Mirrors verify() so a single source of truth.
     */
    public function verifyDetailed(
        ?string $token,
        ?string $answer,
        ?string $honeypot = null,
        ?string $powNonce = null,
        ?string $ip = null,
    ): string {
        if ($ip !== null) {
            $limiterKey = self::VERIFY_LIMIT_KEY.'|'.$ip;
            if (RateLimiter::tooManyAttempts($limiterKey, self::VERIFY_MAX_ATTEMPTS)) {
                return 'rate';
            }
            RateLimiter::hit($limiterKey, self::VERIFY_DECAY_SECONDS);
        }

        if (! empty($honeypot)) {
            if ($token) {
                Cache::forget(self::CACHE_PREFIX.$token);
            }
            return 'honeypot';
        }
        if (! $token || ! is_string($answer) || trim($answer) === '') {
            return 'answer';
        }

        $entry = Cache::pull(self::CACHE_PREFIX.$token);
        if (! is_array($entry)) {
            return 'token';
        }

        if (! isset($entry['answer']) || (int) trim($answer) !== (int) $entry['answer']) {
            return 'answer';
        }

        $createdAtMs = (int) ($entry['created_at_ms'] ?? 0);
        $nowMs = (int) round(microtime(true) * 1000);
        if ($createdAtMs <= 0 || ($nowMs - $createdAtMs) < self::MIN_SUBMIT_MS) {
            return 'timing';
        }

        $salt = (string) ($entry['pow_salt'] ?? '');
        $difficulty = (int) ($entry['pow_difficulty'] ?? 0);
        if ($salt === '' || $difficulty <= 0 || ! is_string($powNonce) || $powNonce === '' || strlen($powNonce) > 32) {
            return 'pow';
        }
        $digest = hash('sha256', $salt.':'.$powNonce);
        $needed = str_repeat('0', $difficulty);
        if (! str_starts_with($digest, $needed)) {
            return 'pow';
        }

        return 'ok';
    }
}
