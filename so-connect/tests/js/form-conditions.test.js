import test from "node:test";
import assert from "node:assert/strict";
import { formConditions } from "../../resources/js/components/form-conditions.js";

function requiredWrapper(control) {
    return {
        querySelectorAll: () => [control],
        querySelector: () => control,
        scrollIntoView: () => {
            control.scrolled = true;
        },
    };
}

function panelWith(wrapper) {
    return {
        querySelectorAll: () => wrapper ? [wrapper] : [],
    };
}

test("final organization submission reveals a missing field from an earlier hidden step", () => {
    const control = {
        type: "text",
        value: "",
        disabled: false,
        focus: () => {
            control.focused = true;
        },
    };
    const wrapper = requiredWrapper(control);
    const component = formConditions({ applicationStep: 4, applicationSteps: 4 });
    let submitted = 0;

    component.$root = {
        querySelector: (selector) => selector.includes('="1"') ? panelWith(wrapper) : panelWith(null),
        submit: () => {
            submitted += 1;
        },
    };
    component.$nextTick = (callback) => callback();

    component.submitApplication();

    assert.equal(submitted, 0);
    assert.equal(component.applicationStep, 1);
    assert.equal(component.applicationStepError, "Complete all required fields before submitting.");
    assert.equal(control.focused, true);
    assert.equal(control.scrolled, true);
});

test("final organization submission posts after every required step is complete", () => {
    const control = {
        type: "text",
        value: "Complete",
        disabled: false,
        focus: () => {},
    };
    const component = formConditions({ applicationStep: 4, applicationSteps: 4 });
    let submitted = 0;

    component.$root = {
        querySelector: (selector) => selector.includes('="1"')
            ? panelWith(requiredWrapper(control))
            : panelWith(null),
        submit: () => {
            submitted += 1;
        },
    };
    component.$nextTick = (callback) => callback();

    component.submitApplication();
    component.submitApplication();

    assert.equal(submitted, 1);
    assert.equal(component.submitting, true);
    assert.equal(component.applicationStepError, "");
});

test("conditionally disabled required fields do not block final submission", () => {
    const control = {
        type: "file",
        files: [],
        disabled: true,
        focus: () => {},
    };
    const component = formConditions({ applicationStep: 4, applicationSteps: 4 });
    let submitted = 0;

    component.$root = {
        querySelector: (selector) => selector.includes('="3"')
            ? panelWith(requiredWrapper(control))
            : panelWith(null),
        submit: () => {
            submitted += 1;
        },
    };
    component.$nextTick = (callback) => callback();

    component.submitApplication();

    assert.equal(submitted, 1);
});
