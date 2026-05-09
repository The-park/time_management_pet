/**
 * Live password validation. Runs on keyup so the user sees a checklist
 * update immediately instead of waiting until they hit Submit.
 *
 * Activation: any <input type="password" data-pw-strength> on the page.
 * If the form also contains a sibling/descendant <input id="…confirmation">
 * (or name="password_confirmation"), a "passwords match" rule is added.
 *
 * Rules are kept in sync with App\Actions\Fortify\PasswordValidationRules
 * (Password::min(10)->letters()->numbers() + 'confirmed'). Keep server +
 * client requirements aligned: server is source of truth, client just
 * mirrors so the user can see progress.
 *
 * If the form has a submit button, it gets disabled until every rule
 * passes — but only when all password fields are non-empty so the form
 * doesn't block on first paint.
 */
(() => {
    const RULES = [
        { id: 'len',   label: 'At least 10 characters',          test: (p) => p.length >= 10 },
        { id: 'lett',  label: 'Includes a letter',               test: (p) => /[A-Za-z]/.test(p) },
        { id: 'num',   label: 'Includes a number',               test: (p) => /\d/.test(p) },
    ];

    const CHECK = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><polyline points="20 6 9 17 4 12"/></svg>`;
    const DOT   = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3 w-3"><circle cx="12" cy="12" r="3"/></svg>`;

    const findConfirm = (input) => {
        const form = input.form;
        if (!form) return null;
        return form.querySelector('input[name="password_confirmation"]')
            || form.querySelector('#password_confirmation')
            || null;
    };

    const findSubmit = (input) => {
        const form = input.form;
        if (!form) return null;
        return form.querySelector('button[type="submit"], input[type="submit"]');
    };

    const buildChecklist = (input, hasConfirm) => {
        const list = document.createElement('ul');
        list.dataset.pwStrengthList = '1';
        list.className = 'mt-1.5 space-y-0.5 text-xs text-slate-400';
        list.setAttribute('aria-live', 'polite');

        const items = RULES.slice();
        if (hasConfirm) {
            items.push({ id: 'match', label: 'Passwords match', test: null });
        }

        for (const rule of items) {
            const li = document.createElement('li');
            li.dataset.pwRule = rule.id;
            li.className = 'flex items-center gap-1.5';
            li.innerHTML = `<span data-pw-icon class="text-slate-500">${DOT}</span><span>${rule.label}</span>`;
            list.appendChild(li);
        }
        input.parentNode.insertBefore(list, input.nextSibling);
        return list;
    };

    const setRuleState = (list, ruleId, ok, dirty) => {
        const li = list.querySelector(`[data-pw-rule="${ruleId}"]`);
        if (!li) return;
        const icon = li.querySelector('[data-pw-icon]');
        if (ok) {
            li.className = 'flex items-center gap-1.5 text-emerald-400';
            if (icon) {
                icon.className = 'text-emerald-400';
                icon.innerHTML = CHECK;
            }
        } else if (dirty) {
            li.className = 'flex items-center gap-1.5 text-rose-300';
            if (icon) {
                icon.className = 'text-rose-300';
                icon.innerHTML = DOT;
            }
        } else {
            li.className = 'flex items-center gap-1.5 text-slate-400';
            if (icon) {
                icon.className = 'text-slate-500';
                icon.innerHTML = DOT;
            }
        }
    };

    const enhance = (input) => {
        if (!(input instanceof HTMLInputElement)) return;
        if (input.type !== 'password') return;
        if (input.dataset.pwStrengthAttached === '1') return;
        input.dataset.pwStrengthAttached = '1';

        const confirm = findConfirm(input);
        const submit = findSubmit(input);
        const list = buildChecklist(input, !!confirm);

        const evaluate = () => {
            const pwd = input.value;
            const cfm = confirm ? confirm.value : '';
            const pwdDirty = pwd.length > 0;
            const cfmDirty = cfm.length > 0;
            // On forms like admin/administrators/edit the password field
            // is optional — leaving both blank means "keep current". Skip
            // gating in that case so we don't block legitimate edits, and
            // hide the checklist until the user starts typing.
            const optional = !input.required;
            const skipGate = optional && !pwdDirty && !cfmDirty;
            if (optional) {
                list.classList.toggle('hidden', skipGate);
            }

            let allOk = true;
            for (const rule of RULES) {
                const ok = rule.test(pwd);
                setRuleState(list, rule.id, ok, pwdDirty);
                if (!ok) allOk = false;
            }
            if (confirm) {
                const ok = pwd.length > 0 && pwd === cfm;
                setRuleState(list, 'match', ok, cfmDirty);
                if (!ok) allOk = false;
            }

            if (submit) {
                // Only gate the submit button if this script "owns" the
                // gating. If another module (e.g. time12) already manages
                // the disabled state via [data-time12-gate], we AND our
                // requirement on top by setting a data attribute the
                // gating script reads — falling back to direct disabled
                // toggle when no gate is present.
                const gateOwner = submit.dataset.time12Gate;
                const ready = skipGate || (allOk && pwdDirty && (!confirm || cfmDirty));
                const prev = submit.dataset.pwReady;
                submit.dataset.pwReady = ready ? '1' : '0';
                if (gateOwner) {
                    // Nudge time12 to re-evaluate the gate. Easiest signal
                    // is dispatching an input event on any group member.
                    if (prev !== submit.dataset.pwReady) {
                        const member = document.querySelector(`[data-time12-group="${gateOwner}"]`);
                        member?.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                } else {
                    submit.disabled = !ready;
                }
            }
        };

        input.addEventListener('keyup', evaluate);
        input.addEventListener('input', evaluate);
        if (confirm) {
            confirm.addEventListener('keyup', evaluate);
            confirm.addEventListener('input', evaluate);
        }
        evaluate();
    };

    const scan = (root) => {
        const inputs = (root || document).querySelectorAll('input[type="password"][data-pw-strength]');
        inputs.forEach(enhance);
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
                if (node.matches?.('input[type="password"][data-pw-strength]')) {
                    enhance(node);
                } else {
                    scan(node);
                }
            }
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
