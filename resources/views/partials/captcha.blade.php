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

{{-- Proof-of-work + refresh wiring. Inlined per-include so multiple
     captcha widgets on a page each work independently. The IIFE shadows
     all locals to keep the global scope clean. No external deps. --}}
<script>
(function(){
    // Find the widget we just rendered. document.currentScript points at
    // this <script> tag at parse time; we walk up to the preceding
    // [data-captcha-widget] sibling. Falling back to last widget on the
    // page handles the (currently unused) case of script defer/move.
    var script = document.currentScript;
    var widget = null;
    if (script) {
        var node = script.previousElementSibling;
        while (node && !widget) {
            if (node.matches && node.matches('[data-captcha-widget]')) widget = node;
            node = node.previousElementSibling;
        }
    }
    if (!widget) {
        var all = document.querySelectorAll('[data-captcha-widget]');
        widget = all[all.length - 1] || null;
    }
    if (!widget) return;

    var form = widget.closest('form');
    var tokenInput = widget.querySelector('[data-captcha-token]');
    var nonceInput = widget.querySelector('[data-captcha-nonce]');
    var img = widget.querySelector('[data-captcha-img]');
    var spinner = widget.querySelector('[data-captcha-spinner]');
    var refreshBtn = widget.querySelector('[data-captcha-refresh]');

    var salt = widget.getAttribute('data-pow-salt') || '';
    var difficulty = parseInt(widget.getAttribute('data-pow-difficulty') || '4', 10) || 4;

    // Track the in-flight PoW job. {salt, cancel} so a refresh aborts
    // the prior loop.
    var job = null;

    function setSpinner(visible) {
        if (!spinner) return;
        if (visible) spinner.classList.remove('hidden');
        else spinner.classList.add('hidden');
    }

    function setSubmitting(disabled) {
        if (!form) return;
        var submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        for (var i = 0; i < submits.length; i++) {
            submits[i].disabled = !!disabled;
        }
    }

    // SHA-256: prefer Web Crypto (window.crypto.subtle) for the one-shot
    // verification path on first use; for the hot proof-of-work loop we
    // use the synchronous fallback below because batching ~16k async
    // digests through microtasks is dramatically slower than running a
    // tight in-JS hash loop. The chosen function is identical SHA-256
    // either way — the server re-verifies, so client impl doesn't matter
    // for security.
    function digestHex(input) {
        return sha256Fallback(input);
    }

    // ── Tiny pure-JS SHA-256 fallback ─────────────────────────────────
    // Used only when window.crypto.subtle is missing. Adequate for the
    // proof-of-work; not used for anything security-critical client-side.
    function sha256Fallback(str) {
        function rotr(n, x) { return (x >>> n) | (x << (32 - n)); }
        var K = [
            0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,
            0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,
            0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,
            0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,
            0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,
            0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,
            0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,
            0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2
        ];
        var H = [0x6a09e667,0xbb67ae85,0x3c6ef372,0xa54ff53a,0x510e527f,0x9b05688c,0x1f83d9ab,0x5be0cd19];
        // UTF-8 encode
        var bytes = [];
        for (var i = 0; i < str.length; i++) {
            var c = str.charCodeAt(i);
            if (c < 0x80) bytes.push(c);
            else if (c < 0x800) { bytes.push(0xc0 | (c >> 6), 0x80 | (c & 0x3f)); }
            else { bytes.push(0xe0 | (c >> 12), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f)); }
        }
        var len = bytes.length;
        bytes.push(0x80);
        while ((bytes.length % 64) !== 56) bytes.push(0);
        var bitLen = len * 8;
        for (var k = 7; k >= 0; k--) bytes.push((bitLen >>> (k * 8)) & 0xff);
        for (var b = 0; b < bytes.length; b += 64) {
            var W = new Array(64);
            for (var t = 0; t < 16; t++) {
                W[t] = (bytes[b+4*t]<<24)|(bytes[b+4*t+1]<<16)|(bytes[b+4*t+2]<<8)|(bytes[b+4*t+3]);
                W[t] = W[t] >>> 0;
            }
            for (t = 16; t < 64; t++) {
                var s0 = rotr(7, W[t-15]) ^ rotr(18, W[t-15]) ^ (W[t-15] >>> 3);
                var s1 = rotr(17, W[t-2]) ^ rotr(19, W[t-2]) ^ (W[t-2] >>> 10);
                W[t] = (W[t-16] + s0 + W[t-7] + s1) >>> 0;
            }
            var a=H[0],bb=H[1],c=H[2],d=H[3],e=H[4],f=H[5],g=H[6],h=H[7];
            for (t = 0; t < 64; t++) {
                var S1 = rotr(6,e) ^ rotr(11,e) ^ rotr(25,e);
                var ch = (e & f) ^ ((~e) & g);
                var temp1 = (h + S1 + ch + K[t] + W[t]) >>> 0;
                var S0 = rotr(2,a) ^ rotr(13,a) ^ rotr(22,a);
                var mj = (a & bb) ^ (a & c) ^ (bb & c);
                var temp2 = (S0 + mj) >>> 0;
                h=g; g=f; f=e; e=(d + temp1) >>> 0; d=c; c=bb; bb=a; a=(temp1 + temp2) >>> 0;
            }
            H[0]=(H[0]+a)>>>0; H[1]=(H[1]+bb)>>>0; H[2]=(H[2]+c)>>>0; H[3]=(H[3]+d)>>>0;
            H[4]=(H[4]+e)>>>0; H[5]=(H[5]+f)>>>0; H[6]=(H[6]+g)>>>0; H[7]=(H[7]+h)>>>0;
        }
        var hex = '';
        for (var idx = 0; idx < 8; idx++) {
            var v = H[idx].toString(16);
            while (v.length < 8) v = '0' + v;
            hex += v;
        }
        return hex;
    }

    // Run the proof-of-work for the current salt. Cooperative: yields
    // every 200 iterations so the UI doesn't freeze. Difficulty 4 averages
    // ~16k tries (~80ms on a modern phone, a few hundred ms on older ones).
    // Returns a Promise that resolves with the found nonce (string) or
    // null if the job was cancelled.
    function runPow(currentSalt) {
        var needed = ''; for (var i = 0; i < difficulty; i++) needed += '0';
        var cancelled = false;
        var ctx = { salt: currentSalt, cancel: function(){ cancelled = true; } };
        job = ctx;
        setSpinner(true);

        return new Promise(function(resolve){
            var nonce = 0;
            // Safety cap: with difficulty 4 the expected work is ~16k tries
            // and 99.99% of attempts finish under ~150k. If we somehow miss,
            // give up at 5M to avoid a runaway loop.
            var maxNonce = 5000000;
            function step() {
                if (cancelled) { setSpinner(false); resolve(null); return; }
                var batchEnd = Math.min(nonce + 200, maxNonce);
                while (nonce < batchEnd) {
                    var hex = digestHex(currentSalt + ':' + nonce);
                    if (hex.indexOf(needed) === 0) {
                        var found = String(nonce);
                        if (nonceInput) nonceInput.value = found;
                        setSpinner(false);
                        resolve(found);
                        return;
                    }
                    nonce++;
                }
                if (nonce >= maxNonce) { setSpinner(false); resolve(null); return; }
                setTimeout(step, 0);
            }
            step();
        });
    }

    // Latest in-flight PoW promise so the submit handler can await it.
    var currentPowPromise = null;

    function startPow() {
        if (job && job.cancel) job.cancel();
        if (nonceInput) nonceInput.value = '';
        currentPowPromise = runPow(salt);
        return currentPowPromise;
    }

    // Kick off the PoW once the page is idle, so it doesn't compete with
    // critical first-paint work.
    function deferStart() {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(startPow, { timeout: 1500 });
        } else {
            setTimeout(startPow, 100);
        }
    }
    if (document.readyState === 'complete' || document.readyState === 'interactive') deferStart();
    else document.addEventListener('DOMContentLoaded', deferStart);

    // Submit guard: if the PoW hasn't finished by the time the user clicks
    // submit, hold the form open and finish first.
    if (form) {
        form.addEventListener('submit', function(ev){
            if (nonceInput && nonceInput.value) return; // already done — let it through
            ev.preventDefault();
            setSubmitting(true);
            setSpinner(true);
            // If no job is running (e.g. refresh failed or PoW deferred
            // hasn't started yet), kick one off now.
            var p = currentPowPromise || startPow();
            p.then(function(found){
                setSubmitting(false);
                setSpinner(false);
                if (found && nonceInput && nonceInput.value) {
                    form.submit();
                }
                // If found is null the job was cancelled or hit the cap;
                // user can hit Refresh challenge and try again.
            });
        });
    }

    // Refresh button: swaps token + image + restarts PoW with new salt.
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function(){
            var url = widget.getAttribute('data-captcha-refresh-url');
            if (!url) return;
            refreshBtn.disabled = true;
            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data || !data.token) return;
                    if (tokenInput) tokenInput.value = data.token;
                    if (nonceInput) nonceInput.value = '';
                    if (img) img.src = data.image_url + (data.image_url.indexOf('?')>=0?'&':'?') + 'r=' + Date.now();
                    salt = data.pow_salt || salt;
                    difficulty = parseInt(data.pow_difficulty, 10) || difficulty;
                    widget.setAttribute('data-pow-salt', salt);
                    widget.setAttribute('data-pow-difficulty', String(difficulty));
                    startPow();
                })
                .catch(function(){ /* surfacing nothing — user can retry */ })
                .then(function(){ refreshBtn.disabled = false; });
        });
    }
})();
</script>
