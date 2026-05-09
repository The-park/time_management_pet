<?php

namespace App\Rules;

use App\Services\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

/**
 * Pulls captcha_token + captcha_hp from the current request and verifies
 * the user-provided answer against CaptchaService. Rejects on bad/expired
 * token or filled honeypot.
 */
class Captcha implements ValidationRule
{
    public function __construct(
        private CaptchaService $captcha,
        private Request $request,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $token = $this->request->input('captcha_token');
        $honeypot = $this->request->input('captcha_hp');

        if (! $this->captcha->verify($token, is_string($value) ? $value : null, $honeypot)) {
            $fail("The CAPTCHA answer is incorrect. Please try the new one below.");
        }
    }
}
