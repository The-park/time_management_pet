/**
 * Show/hide toggle for every <input type="password"> on the page.
 *
 * Runs at DOMContentLoaded and again whenever new password inputs are
 * inserted (e.g. by a Livewire/Alpine update). Each password input gets
 * wrapped in a relative container with an eye / eye-off button on the
 * right edge. Click the button → flips type between password/text and
 * swaps the icon.
 *
 * Skipped automatically:
 *   - inputs already enhanced (data-pw-toggle-attached)
 *   - inputs marked <input type="password" data-no-pw-toggle>
 *   - hidden / display:none inputs
 */
(() => {
    const EYE_OPEN = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>`;
    const EYE_OFF  = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-10-7-10-7a19.79 19.79 0 0 1 4.22-5.94"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 10 7 10 7a19.71 19.71 0 0 1-3.16 4.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="2" y1="2" x2="22" y2="22"/></svg>`;

    const enhance = (input) => {
        if (!(input instanceof HTMLInputElement)) return;
        if (input.type !== 'password') return;
        if (input.dataset.pwToggleAttached === '1') return;
        if (input.dataset.noPwToggle !== undefined) return;
        // Don't enhance hidden inputs (e.g. spam honeypots, internal state)
        const style = window.getComputedStyle(input);
        if (style.display === 'none' || style.visibility === 'hidden') return;

        input.dataset.pwToggleAttached = '1';

        // Wrap the input so we can absolutely-position the toggle button.
        // Preserve the original parent + sibling order.
        const wrapper = document.createElement('div');
        wrapper.className = 'relative';
        wrapper.style.display = 'block';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        // Make room for the icon on the right of the input.
        // Most inputs in this app already have px-3 padding; we add the right
        // pad without disturbing left padding by setting paddingRight inline.
        const currentPadRight = parseFloat(style.paddingRight) || 0;
        if (currentPadRight < 36) {
            input.style.paddingRight = '36px';
        }

        // Build the toggle button.
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.tabIndex = -1;     // keep keyboard tab order on the input itself
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('aria-pressed', 'false');
        btn.className = 'absolute inset-y-0 right-0 flex items-center justify-center pr-2.5 pl-2 text-slate-400 hover:text-slate-100 transition-colors cursor-pointer bg-transparent border-0';
        btn.style.height = '100%';
        btn.innerHTML = EYE_OPEN;

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.innerHTML = showing ? EYE_OPEN : EYE_OFF;
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
        });

        wrapper.appendChild(btn);
    };

    const enhanceAll = (root = document) => {
        root.querySelectorAll('input[type="password"]').forEach(enhance);
    };

    // Initial pass once the DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => enhanceAll());
    } else {
        enhanceAll();
    }

    // Re-scan when nodes are inserted (Livewire/Alpine renders, modal opens, etc.)
    const mo = new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (!(node instanceof Element)) continue;
                if (node.matches?.('input[type="password"]')) enhance(node);
                node.querySelectorAll?.('input[type="password"]').forEach(enhance);
            }
        }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
})();
