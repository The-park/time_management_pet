<?php

namespace App\Rules;

use App\Services\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

/**
 * Pulls captcha_token + captcha_hp + captcha_pow_nonce from the current
 * request and verifies the user-provided answer against CaptchaService.
 * Rejects on bad/expired token, filled honeypot, missing or invalid
 * proof-of-work nonce, or too-fast submission. Surfaces distinct messages
 * so the user knows whether to refresh the challenge or slow down.
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
        $powNonce = $this->request->input('captcha_pow_nonce');

        $result = $this->captcha->verifyDetailed(
            is_string($token) ? $token : null,
            is_string($value) ? $value : null,
            is_string($honeypot) ? $honeypot : null,
            is_string($powNonce) ? $powNonce : null,
            $this->request->ip(),
        );

        switch ($result) {
            case 'ok':
                return;
            case 'pow':
                $fail("Your browser couldn't complete the verification challenge. Refresh and try again.");
                return;
            case 'timing':
                $fail('Form submitted too quickly — please try again.');
                return;
            case 'rate':
                $fail('Too many CAPTCHA attempts from your network. Please wait a minute and try again.');
                return;
            // 'answer', 'token', 'honeypot' all collapse to the original
            // user-facing message so we don't leak which check failed.
            default:
                $fail('The CAPTCHA answer is incorrect. Please try the new one below.');
                return;
        }
    }
}
