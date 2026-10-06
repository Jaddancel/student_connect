import test from "node:test";
import assert from "node:assert/strict";
import { postViewer } from "../../resources/js/components/post-viewer.js";

test("gallery arrows show one image at a time and wrap in both directions", () => {
    const viewer = postViewer();
    viewer.post = { images: ["one.jpg", "two.jpg", "three.jpg"] };
    assert.equal(viewer.currentImage, "one.jpg");
    viewer.changeImage(1);
    assert.equal(viewer.currentImage, "two.jpg");
    viewer.changeImage(1);
    viewer.changeImage(1);
    assert.equal(viewer.currentImage, "one.jpg");
    viewer.imageFailed = true;
    viewer.changeImage(-1);
    assert.equal(viewer.currentImage, "three.jpg");
    assert.equal(viewer.imageFailed, false);
});

test("empty and single-image posts do not advance", () => {
    const viewer = postViewer();
    viewer.changeImage(1);
    assert.equal(viewer.currentImage, "");
    viewer.post = { images: ["one.jpg"] };
    viewer.changeImage(-1);
    assert.equal(viewer.index, 0);
});

test("keyboard arrows navigate without interfering with media controls", () => {
    const viewer = postViewer();
    viewer.post = { images: ["one.jpg", "two.jpg"] };
    let prevented = false;
    viewer.handleKey({
        key: "ArrowRight",
        target: { closest: () => null },
        preventDefault: () => { prevented = true; },
    });
    assert.equal(viewer.index, 1);
    assert.equal(prevented, true);
    viewer.handleKey({ key: "ArrowLeft", target: { closest: () => ({}) } });
    assert.equal(viewer.index, 1);
});
