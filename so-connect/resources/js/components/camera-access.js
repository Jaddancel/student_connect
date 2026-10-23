/**
 * Open a same-origin camera with a rear-camera preference and a generic-camera
 * fallback. Permissions Policy and secure-context failures are translated into
 * messages that tell the user the real problem instead of always blaming the
 * browser permission toggle.
 */
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
        // Ask for up to 1440p: the ID wizard crops an upright card out of a
        // landscape frame, so the card only gets the frame's *height* in pixels
        // — 720p left it ~400×650, too small to read course/address text. The
        // browser picks the closest mode the camera supports.
        return await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 2560 },
                height: { ideal: 1440 },
            },
        });
    } catch (error) {
        // Permission, policy, OS privacy, and in-use failures will not improve
        // with different constraints. Preserve them for an accurate message.
        if (['NotAllowedError', 'SecurityError', 'NotReadableError'].includes(error?.name)) {
            throw error;
        }

        // Some desktop/older mobile browsers reject facingMode even though a
        // usable webcam exists. Ask for any camera as the compatibility path.
        return navigator.mediaDevices.getUserMedia({ audio: false, video: true });
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
