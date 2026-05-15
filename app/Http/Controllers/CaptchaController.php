<?php

namespace App\Http\Controllers;

use App\Services\CaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Public endpoints that back the SVG captcha image and the AJAX
 * "refresh challenge" button in the captcha partial. Both routes are
 * intentionally unauthenticated — the captcha is used on /login, /register
 * and /contact, all of which are guest-accessible.
 */
class CaptchaController extends Controller
{
    public function __construct(private CaptchaService $captcha) {}

    /**
     * GET /captcha/img/{token}.svg
     * Streams the rendered SVG for an existing challenge. 404s if the
     * token isn't in cache anymore (expired / single-use already consumed).
     */
    public function image(string $token): Response
    {
        return $this->captcha->renderSvg($token);
    }

    /**
     * GET /captcha/refresh
     * Mints a brand-new challenge and returns the metadata the partial
     * needs to swap the image + hidden inputs without a page reload.
     */
    public function refresh(): JsonResponse
    {
        return response()->json($this->captcha->challenge());
    }
}
