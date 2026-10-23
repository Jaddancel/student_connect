/**
 * Open a same-origin camera with a rear-camera preference and a generic-camera
 * fallback. Permissions Policy and secure-context failures are translated into
 * messages that tell the user the real problem instead of always blaming the
 * browser permission toggle.
 */
const DOCUMENT_CAMERA_CONSTRAINTS = {
    facingMode: { ideal: 'environment' },
    // ID/waiver text occupies only part of the frame. Prefer a 4K source so
    // the cropped document still has enough pixels for a sharp preview/OCR.
    // These are ideals, so lower-resolution cameras remain usable.
    width: { ideal: 3840 },
    height: { ideal: 2160 },
};

/**
 * Ask image-capable cameras to keep the document sharp. Image-capture
 * constraints are not implemented consistently across browsers, so every
 * capability is checked and this optimization must never prevent capture.
 */
async function optimizeDocumentCamera(stream) {
    const track = stream.getVideoTracks()[0];
    if (!track) return stream;

    // Where supported, tell the browser this track contains fine detail rather
    // than motion. Browsers that do not expose contentHint simply ignore it.
    try {
        track.contentHint = 'detail';
    } catch (_) {
        // Optional hint only.
    }

    if (typeof track.getCapabilities !== 'function' || typeof track.applyConstraints !== 'function') {
        return stream;
    }

    try {
        const capabilities = track.getCapabilities();
        const advanced = {};

        if (capabilities.focusMode?.includes('continuous')) {
            advanced.focusMode = 'continuous';
        }
        if (capabilities.exposureMode?.includes('continuous')) {
            advanced.exposureMode = 'continuous';
        }
        if (capabilities.whiteBalanceMode?.includes('continuous')) {
            advanced.whiteBalanceMode = 'continuous';
        }

        if (Object.keys(advanced).length) {
            await track.applyConstraints({ advanced: [advanced] });
        }
    } catch (_) {
        // The live stream is still valid when a browser advertises but rejects
        // one of these optional image-capture constraints.
    }

    return stream;
}

export async function openPreferredCamera() {
    if (!window.isSecureContext) {
        const error = new Error('Camera capture requires HTTPS or localhost.');
        error.name = 'InsecureContextError';
        throw error;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        const error = new Error('Camera capture is unavailable in this browser.');
        error.name = 'UnsupportedCameraError';
        throw error;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: DOCUMENT_CAMERA_CONSTRAINTS,
        });
        return optimizeDocumentCamera(stream);
    } catch (error) {
        // Permission, policy, OS privacy, and in-use failures will not improve
        // with different constraints. Preserve them for an accurate message.
        if (['NotAllowedError', 'SecurityError', 'NotReadableError'].includes(error?.name)) {
            throw error;
        }

        // Some desktop/older mobile browsers reject facingMode even though a
        // usable webcam exists. Ask for any camera as the compatibility path.
        const stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
        return optimizeDocumentCamera(stream);
    }
}

export function cameraAccessMessage(error) {
    switch (error?.name) {
        case 'InsecureContextError':
            return 'Camera access requires HTTPS or localhost. Open the secure version of this page, or upload a photo instead.';
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Camera access is blocked by the browser or device. Allow this site in both browser and device camera settings, then reload the page.';
        case 'NotFoundError':
        case 'DevicesNotFoundError':
            return 'No available camera was detected. Connect or enable a camera, or upload a photo instead.';
        case 'NotReadableError':
        case 'TrackStartError':
            return 'The camera is being used by another application. Close the other camera app and try again.';
        case 'AbortError':
            return 'The camera was interrupted before it could start. Please try again.';
        case 'UnsupportedCameraError':
            return 'This browser cannot access the camera. Try a current browser or upload a photo instead.';
        default:
            return 'The camera could not start. Reload the page and try again, or upload a photo instead.';
    }
}
