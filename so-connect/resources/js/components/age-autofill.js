/**
 * Live "Age - Computed from Birthday" autofill for the Sign Up form.
 *
 * A field mapped to the `age_from_birthday` universal key derives its value
 * from the form's own birthday date field (universal key `birthday`). At
 * sign-up there is no profile yet, so the age is computed client-side as the
 * birthday changes and authoritatively recomputed server-side at submit
 * (see FormRenderController::submit).
 *
 * Both markers sit on the field wrapper (see components/form/fields/field.blade),
 * so this drills into the wrapper for the actual control — the same contract the
 * ID-scan wizard uses. No Alpine coupling: a single delegated listener on the
 * document handles every input/change.
 */
export function registerAgeAutofill() {
    document.addEventListener('input', onSourceEvent);
    document.addEventListener('change', onSourceEvent);
}

function onSourceEvent(event) {
    const target = event.target;
    if (!target || typeof target.closest !== 'function') return;

    const birthdayWrap = target.closest('[data-universal-key="birthday"]');
    if (!birthdayWrap) return;

    const source = controlWithin(birthdayWrap);
    if (!source) return;

    const age = computeAge(source.value);
    document.querySelectorAll('[data-universal-key="age_from_birthday"]').forEach((wrap) => {
        const control = controlWithin(wrap);
        if (!control) return;
        control.value = age === null ? '' : String(age);
        // Keep form-conditions.js value tracking in sync.
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
