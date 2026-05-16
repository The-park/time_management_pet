{{--
  Hardened math + honeypot CAPTCHA. Drop into any form before the submit
  button:
      @include('partials.captcha')
  Server side: validate captcha_answer with the App\Rules\Captcha rule.

  Defences (all server-verified):
    1. The math question is NOT in the HTML. It's rendered as an obfuscated
       SVG by /captcha/img/{token}.svg — humans see the image, scrapers see
       only an <img> tag. The literal question is in the cache + SVG bytes.
    2. A small proof-of-work nonce (SHA-256 with N leading hex zeros) is
       computed in-browser and posted as captcha_pow_nonce. Cheap for one
       human (~80ms on a phone), painful for a scraper farm.
    3. The honeypot text field traps bots that auto-fill every input.

  The submitted hidden inputs are: captcha_token, captcha_pow_nonce,
  captcha_hp. The visible input is captcha_answer. The "Refresh challenge"
  button swaps token + image + pow_salt without a full reload.
--}}
@php
    $captchaSvc = app(\App\Services\CaptchaService::class);
    $challenge = $captchaSvc->challenge();
@endphp

<div class="space-y-1" data-captcha-widget
    data-captcha-refresh-url="{{ route('captcha.refresh') }}"
    data-pow-salt="{{ $challenge['pow_salt'] }}"
    data-pow-difficulty="{{ $challenge['pow_difficulty'] }}">
    <label class="block text-sm mb-1" for="captcha_answer">
        Verify you're human
    </label>
    <div class="flex items-center gap-3 rounded-md border border-slate-700 bg-slate-900 px-3 py-2">
        <img data-captcha-img
            src="{{ $challenge['image_url'] }}"
            alt="Math problem — refresh if you can't read it"
            width="210" height="56"
            class="shrink-0 rounded-md bg-slate-200">
        <input id="captcha_answer" name="captcha_answer" type="text" inputmode="numeric"
            autocomplete="off" required
            placeholder="Answer"
            class="flex-1 rounded-md bg-slate-950 border border-slate-700 px-2 py-1 text-slate-100 focus:outline-none focus:border-[var(--chrono-blue,#22d3ee)]/60">
    </div>
    @error('captcha_answer')
        <p class="text-sm text-rose-400 mt-1">{{ $message }}</p>
    @enderror
    <div class="flex items-center justify-between gap-3 mt-1">
        <p class="text-[0.65rem] text-slate-500">
            Helps keep automated bots out of the form.
        </p>
        <button type="button" data-captcha-refresh
            class="text-[0.65rem] uppercase tracking-[0.15em] text-slate-400 hover:text-slate-200 underline-offset-2 hover:underline">
            Refresh challenge
        </button>
    </div>

    {{-- Hidden state. captcha_token + captcha_pow_nonce are populated /
         rotated by the inline script; the form posts them as-is. --}}
    <input type="hidden" name="captcha_token" data-captcha-token value="{{ $challenge['token'] }}">
    <input type="hidden" name="captcha_pow_nonce" data-captcha-nonce value="">

    {{-- Inline spinner shown while the proof-of-work nonce is being
         computed. The script flips this visible/invisible. --}}
    <p data-captcha-spinner class="text-[0.7rem] text-slate-400 mt-1 hidden" aria-live="polite">
        <span class="inline-block animate-pulse">●</span>
        Verifying you're human…
    </p>

    {{-- Honeypot. Bots that scrape every input fill this; humans never see
         it. CSS-hidden in three ways for resilience (display:none alone
         is suspicious to some bots — combining it with off-screen + zero
         opacity catches more cases). --}}
    <div aria-hidden="true" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden; opacity:0;">
        <label for="captcha_hp">Website (leave blank)</label>
        <input id="captcha_hp" name="captcha_hp" type="text" tabindex="-1" autocomplete="off" value="">
    </div>
</div>

