<?php

use App\Services\CaptchaService;
use Illuminate\Support\Facades\Cache;

it('serves the captcha image as image/svg+xml without leaking digits in headers', function () {
    /** @var CaptchaService $captcha */
    $captcha = app(CaptchaService::class);
    $challenge = $captcha->challenge();

    $response = $this->get(route('captcha.image', ['token' => $challenge['token']]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/svg+xml');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    // Pull the cached question back so we know what the SVG encodes.
    // We need a fresh challenge for this assertion since the SVG render
    // doesn't pull, but a different token would have different digits.
    // We only assert that the question literal isn't leaked into the
    // HTTP response headers — the SVG body is allowed to contain the
    // characters (that's the whole point of rendering it).
    $allHeaders = '';
    foreach ($response->headers->all() as $key => $values) {
        $allHeaders .= $key.': '.implode(',', $values).'  ';
    }
    // The literal phrase 'What is' should never appear in headers.
    expect($allHeaders)->not->toContain('What is');

    // SVG content sanity: starts with <?xml or <svg.
    $body = $response->getContent();
    expect($body)->toMatch('/^<\?xml/');
    expect($body)->toContain('<svg');
});

it('rejects verify when the proof-of-work nonce is missing', function () {
    /** @var CaptchaService $captcha */
    $captcha = app(CaptchaService::class);
    $challenge = $captcha->challenge();

    // Pull the cache entry to learn the correct answer (we can't read
    // it from the challenge() return any more — by design).
    $entry = Cache::get('captcha:'.$challenge['token']);
    expect($entry)->toBeArray();
    $answer = (string) $entry['answer'];

    // Re-stuff the cache because we used Cache::get not pull, but verify
    // calls Cache::pull which would have removed it on first try anyway.
    // Refresh the entry with an artificially-old created_at so the
    // timing gate passes.
    Cache::put('captcha:'.$challenge['token'], array_merge($entry, [
        'created_at_ms' => (int) round(microtime(true) * 1000) - 5000,
    ]), now()->addMinutes(15));

    // Correct answer, no honeypot, BUT no proof-of-work nonce → should fail.
    $ok = $captcha->verify($challenge['token'], $answer, null, null, null);
    expect($ok)->toBeFalse();
});
