const MERIDIEM_TAIL = /[ap]\s*\.?\s*m\s*\.?\s*$/i;
const TIME_PATTERN = /^(\d{1,2})(?:\s*[:.]\s*(\d{2}))?\s*([ap])\s*\.?\s*m\s*\.?$/i;

export const parseTime12 = (value) => {
    if (typeof value !== 'string') return null;
    const trimmed = value.trim();
    if (trimmed === '') return null;

    const match = trimmed.match(TIME_PATTERN);
    if (!match) return null;

    let hours = Number(match[1]);
    const minutes = match[2] !== undefined ? Number(match[2]) : 0;
    const period = match[3].toLowerCase();

    if (hours < 1 || hours > 12 || minutes < 0 || minutes > 59) return null;

    if (period === 'a') {
        hours = hours === 12 ? 0 : hours;
    } else if (hours !== 12) {
        hours += 12;
    }

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};

export const hasMeridiem = (value) => MERIDIEM_TAIL.test(String(value ?? '').trim());

export const toMinutes = (hhmm) => {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
};

export const formatTime12 = (hhmm) => {
    if (!hhmm) return '';
    const [h, m] = hhmm.split(':').map(Number);
    const period = h >= 12 ? 'PM' : 'AM';
    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
    return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
};

const bindInput = (display) => {
    if (display.dataset.time12Bound === 'true') return;
    display.dataset.time12Bound = 'true';

    const hidden = display.dataset.time12HiddenId
        ? document.getElementById(display.dataset.time12HiddenId)
        : null;
    const error = display.dataset.time12ErrorId
        ? document.getElementById(display.dataset.time12ErrorId)
        : null;
    const example = display.dataset.time12Example || '10:00 PM';
    const label = display.dataset.time12Label || 'Time';
    const min = display.dataset.time12Min || null;
    const max = display.dataset.time12Max || null;
    const minMinutes = min ? toMinutes(min) : null;
    const maxMinutes = max ? toMinutes(max) : null;
    const rangeText = (min && max) ? `${formatTime12(min)} and ${formatTime12(max)}` : null;

    const dispatchChange = (value) => {
        if (!hidden) return;
        hidden.dispatchEvent(new CustomEvent('time12:change', {
            bubbles: true,
            detail: { value, displayId: display.id },
        }));
    };

    const showError = (msg) => {
        if (hidden && hidden.value !== '') {
            hidden.value = '';
            dispatchChange(null);
        }
        if (error) {
            error.textContent = msg;
            error.classList.remove('hidden');
        }
        display.classList.add('border-rose-500');
        display.dataset.time12Valid = 'false';
    };

    const clearError = () => {
        if (error) error.classList.add('hidden');
        display.classList.remove('border-rose-500');
    };

    const validate = () => {
        const trimmed = display.value.trim();

        if (trimmed === '') {
            if (hidden && hidden.value !== '') {
                hidden.value = '';
                dispatchChange(null);
            }
            clearError();
            display.dataset.time12Valid = 'false';
            return false;
        }

        const normalized = parseTime12(trimmed);

        if (normalized === null) {
            if (!hasMeridiem(trimmed)) {
                showError(`Use 12-hour format with AM/PM, not 24-hour. Example: ${example}.`);
            } else {
                showError(`Couldn't read that as a 12-hour time. Example: ${example}.`);
            }
            return false;
        }

        if (minMinutes !== null || maxMinutes !== null) {
            const mins = toMinutes(normalized);
            if ((minMinutes !== null && mins < minMinutes) || (maxMinutes !== null && mins > maxMinutes)) {
                showError(
                    `${label} must be between ${rangeText}. You entered ${formatTime12(normalized)}.`
                );
                return false;
            }
        }

        if (hidden && hidden.value !== normalized) {
            hidden.value = normalized;
            dispatchChange(normalized);
        }
        clearError();
        display.dataset.time12Valid = 'true';
        return true;
    };

    const refreshGroup = () => {
        const groupName = display.dataset.time12Group;
        if (!groupName) return;
        const members = document.querySelectorAll(`[data-time12-group="${groupName}"]`);
        const allValid = Array.from(members).every((el) => el.dataset.time12Valid === 'true');
        document.querySelectorAll(`[data-time12-gate="${groupName}"]`).forEach((btn) => {
            // Co-operate with the password-strength gate: when its script
            // is active on this form, it sets data-pw-ready="0|1". Both
            // conditions must hold to enable the submit button.
            const pwGate = btn.dataset.pwReady;
            const pwReady = pwGate === undefined ? true : pwGate === '1';
            btn.disabled = !(allValid && pwReady);
        });
    };

    display.addEventListener('input', () => {
        validate();
        refreshGroup();
    });
    display.addEventListener('blur', () => {
        validate();
        refreshGroup();
    });

    validate();
    refreshGroup();
};

export const initTime12Inputs = (root = document) => {
    root.querySelectorAll('[data-time12]').forEach(bindInput);
};

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initTime12Inputs());
    } else {
        initTime12Inputs();
    }

    window.ChronoTime12 = { parseTime12, formatTime12, toMinutes, hasMeridiem, initTime12Inputs };
}
