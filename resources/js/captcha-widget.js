// CAPTCHA proof-of-work + refresh wiring. Runs on all pages via app.js.
(() => {
    const NEEDS_SELECTOR = '[data-captcha-widget]';

    const digestHex = (input) => sha256Fallback(input);

    function sha256Fallback(str) {
        function rotr(n, x) { return (x >>> n) | (x << (32 - n)); }
        const K = [
            0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,
            0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,
            0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,
            0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,
            0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,
            0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,
            0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,
            0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2
        ];
        let H = [0x6a09e667,0xbb67ae85,0x3c6ef372,0xa54ff53a,0x510e527f,0x9b05688c,0x1f83d9ab,0x5be0cd19];
        const bytes = [];
        for (let i = 0; i < str.length; i++) {
            const c = str.charCodeAt(i);
            if (c < 0x80) bytes.push(c);
            else if (c < 0x800) { bytes.push(0xc0 | (c >> 6), 0x80 | (c & 0x3f)); }
            else { bytes.push(0xe0 | (c >> 12), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f)); }
        }
        const len = bytes.length;
        bytes.push(0x80);
        while ((bytes.length % 64) !== 56) bytes.push(0);
        const bitLen = len * 8;
        for (let k = 7; k >= 0; k--) bytes.push((bitLen >>> (k * 8)) & 0xff);
        for (let b = 0; b < bytes.length; b += 64) {
            const W = new Array(64);
            for (let t = 0; t < 16; t++) {
                W[t] = (bytes[b+4*t]<<24)|(bytes[b+4*t+1]<<16)|(bytes[b+4*t+2]<<8)|(bytes[b+4*t+3]);
                W[t] = W[t] >>> 0;
            }
            for (let t = 16; t < 64; t++) {
                const s0 = rotr(7, W[t-15]) ^ rotr(18, W[t-15]) ^ (W[t-15] >>> 3);
                const s1 = rotr(17, W[t-2]) ^ rotr(19, W[t-2]) ^ (W[t-2] >>> 10);
                W[t] = (W[t-16] + s0 + W[t-7] + s1) >>> 0;
            }
            let a=H[0],bb=H[1],c=H[2],d=H[3],e=H[4],f=H[5],g=H[6],h=H[7];
            for (let t = 0; t < 64; t++) {
                const S1 = rotr(6,e) ^ rotr(11,e) ^ rotr(25,e);
                const ch = (e & f) ^ ((~e) & g);
                const temp1 = (h + S1 + ch + K[t] + W[t]) >>> 0;
                const S0 = rotr(2,a) ^ rotr(13,a) ^ rotr(22,a);
                const mj = (a & bb) ^ (a & c) ^ (bb & c);
                const temp2 = (S0 + mj) >>> 0;
                h=g; g=f; f=e; e=(d + temp1) >>> 0; d=c; c=bb; bb=a; a=(temp1 + temp2) >>> 0;
            }
            H[0]=(H[0]+a)>>>0; H[1]=(H[1]+bb)>>>0; H[2]=(H[2]+c)>>>0; H[3]=(H[3]+d)>>>0;
            H[4]=(H[4]+e)>>>0; H[5]=(H[5]+f)>>>0; H[6]=(H[6]+g)>>>0; H[7]=(H[7]+h)>>>0;
        }
        let hex = '';
        for (let idx = 0; idx < 8; idx++) {
            let v = H[idx].toString(16);
            while (v.length < 8) v = '0' + v;
            hex += v;
        }
        return hex;
    }

    function bindWidget(widget) {
        if (!(widget instanceof HTMLElement)) return;
        if (widget.dataset.captchaBound === '1') return;
        widget.dataset.captchaBound = '1';

        const form = widget.closest('form');
        const tokenInput = widget.querySelector('[data-captcha-token]');
        const nonceInput = widget.querySelector('[data-captcha-nonce]');
        const img = widget.querySelector('[data-captcha-img]');
        const spinner = widget.querySelector('[data-captcha-spinner]');
        const refreshBtn = widget.querySelector('[data-captcha-refresh]');

        let salt = widget.getAttribute('data-pow-salt') || '';
        let difficulty = parseInt(widget.getAttribute('data-pow-difficulty') || '4', 10) || 4;

        let job = null;
        let currentPowPromise = null;

        const setSpinner = (visible) => {
            if (!spinner) return;
            if (visible) spinner.classList.remove('hidden');
            else spinner.classList.add('hidden');
        };

        const setSubmitting = (disabled) => {
            if (!form) return;
            const submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submits.forEach((btn) => { btn.disabled = !!disabled; });
        };

        const runPow = (currentSalt) => {
            let needed = '';
            for (let i = 0; i < difficulty; i++) needed += '0';
            let cancelled = false;
            const ctx = { salt: currentSalt, cancel: () => { cancelled = true; } };
            job = ctx;
            setSpinner(true);

            return new Promise((resolve) => {
                let nonce = 0;
                const maxNonce = 5000000;
                const step = () => {
                    if (cancelled) { setSpinner(false); resolve(null); return; }
                    const batchEnd = Math.min(nonce + 200, maxNonce);
                    while (nonce < batchEnd) {
                        const hex = digestHex(currentSalt + ':' + nonce);
                        if (hex.indexOf(needed) === 0) {
                            const found = String(nonce);
                            if (nonceInput) nonceInput.value = found;
                            setSpinner(false);
                            resolve(found);
                            return;
                        }
                        nonce++;
                    }
                    if (nonce >= maxNonce) { setSpinner(false); resolve(null); return; }
                    setTimeout(step, 0);
                };
                step();
            });
        };

        const startPow = () => {
            if (job && job.cancel) job.cancel();
            if (nonceInput) nonceInput.value = '';
            currentPowPromise = runPow(salt);
            return currentPowPromise;
        };

        const deferStart = () => {
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(startPow, { timeout: 1500 });
            } else {
                setTimeout(startPow, 100);
            }
        };

        if (document.readyState === 'complete' || document.readyState === 'interactive') deferStart();
        else document.addEventListener('DOMContentLoaded', deferStart, { once: true });

        if (form && !form.dataset.captchaPowBound) {
            form.dataset.captchaPowBound = '1';
            form.addEventListener('submit', (ev) => {
                if (nonceInput && nonceInput.value) return;
                ev.preventDefault();
                setSubmitting(true);
                setSpinner(true);
                const p = currentPowPromise || startPow();
                p.then((found) => {
                    setSubmitting(false);
                    setSpinner(false);
                    if (found && nonceInput && nonceInput.value) {
                        form.submit();
                    }
                });
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                const url = widget.getAttribute('data-captcha-refresh-url');
                if (!url) return;
                refreshBtn.disabled = true;
                fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then((r) => r.json())
                    .then((data) => {
                        if (!data || !data.token) return;
                        if (tokenInput) tokenInput.value = data.token;
                        if (nonceInput) nonceInput.value = '';
                        if (img) {
                            const joiner = data.image_url.indexOf('?') >= 0 ? '&' : '?';
                            img.src = data.image_url + joiner + 'r=' + Date.now();
                        }
                        salt = data.pow_salt || salt;
                        difficulty = parseInt(data.pow_difficulty, 10) || difficulty;
                        widget.setAttribute('data-pow-salt', salt);
                        widget.setAttribute('data-pow-difficulty', String(difficulty));
                        startPow();
                    })
                    .catch(() => {})
                    .then(() => { refreshBtn.disabled = false; });
            });
        }
    }

    const scan = (root) => {
        (root || document).querySelectorAll(NEEDS_SELECTOR).forEach(bindWidget);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => scan());
    } else {
        scan();
    }

    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (!(node instanceof Element)) continue;
                if (node.matches?.(NEEDS_SELECTOR)) {
                    bindWidget(node);
                } else {
                    scan(node);
                }
            }
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
