/**
 * Live age recalculation helpers used by both the generic form builder and the
 * sign-up profile flow. A number/age field may be configured to calculate from a
 * sibling date field in the same row; the sign-up universal birthday shortcut is
 * still supported for the dedicated `age_from_birthday` autofill.
 */
export function registerAgeAutofill() {
    document.addEventListener('input', onSourceEvent);
    document.addEventListener('change', onSourceEvent);
}

function onSourceEvent(event) {
    const target = event.target;
    if (!target || typeof target.closest !== 'function') return;

    // Dedicated sign-up autofill: birthday -> age_from_birthday.
    const birthdayWrap = target.closest('[data-universal-key="birthday"]');
    if (birthdayWrap) {
        const source = controlWithin(birthdayWrap);
        if (!source) return;

        const age = computeAge(source.value);
        document.querySelectorAll('[data-universal-key="age_from_birthday"]').forEach((wrap) => {
            const control = controlWithin(wrap);
            if (!control) return;
            control.value = age === null ? '' : String(age);
            control.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    // Generic same-row calculation: number/age inputs can target a sibling date field.
    const sourceName = target.name;
    if (!sourceName) return;

    document.querySelectorAll('[data-calculate-from]').forEach((targetEl) => {
        const source = targetEl.dataset.calculateFrom;
        if (!source || source !== sourceName) return;

        const control = controlWithin(targetEl);
        if (!control) return;

        const age = computeAge(target.value);
        control.value = age === null ? '' : String(age);
        control.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

/** The form control for a universal-key wrapper (or the element itself). */
function controlWithin(el) {
    if (!el) return null;
    if (el.matches('input, textarea, select')) return el;
    return el.querySelector('input:not([type="hidden"]), textarea, select');
}

/** Full years between a birthday and today; null for an invalid/future date. */
function computeAge(birthday) {
    if (!birthday) return null;
    const dob = new Date(birthday);
    if (Number.isNaN(dob.getTime())) return null;

    const now = new Date();
    if (dob > now) return null;

    let age = now.getFullYear() - dob.getFullYear();
    const monthDelta = now.getMonth() - dob.getMonth();
    if (monthDelta < 0 || (monthDelta === 0 && now.getDate() < dob.getDate())) {
        age -= 1;
    }

    return age < 0 ? null : age;
}
