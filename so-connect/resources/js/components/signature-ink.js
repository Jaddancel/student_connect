/**
 * Client-side signature ink extraction.
 *
 * Mirrors App\Support\SignatureImage so the preview a user sees matches what
 * the server actually stores. The server re-extracts whatever is posted and is
 * the authority; this exists so a bad photo is caught while the user is still
 * looking at it, rather than after they submit.
 *
 * Signatures are mostly written on paper and photographed, so a single fixed
 * cutoff is not usable: it reads a shadowed page as solid ink and misses a
 * light ballpoint entirely. Every pixel is compared against a blurred copy of
 * its own surroundings instead, and shapes that run off the edge of the frame —
 * a shadow, the edge of the desk, a ruled line — are dropped.
 */

const MAX_EDGE = 1200;
const MIN_INK_CONTRAST = 26;
const MIN_INK_PIXELS = 40;
const MIN_SHAPE_PIXELS = 12;
const MIN_SHAPE_RATIO = 0.02;
const MAX_INK_COVERAGE = 0.35;
const BACKGROUND_WINDOW_DIVISOR = 24;
const CROP_PADDING = 8;
const INK = [20, 20, 30];

/**
 * Extract the ink from an image data-URL and return a cropped, transparent PNG
 * data-URL. Rejects (throws) when the image holds no legible signature.
 */
export async function extractSignatureInk(dataUrl) {
    const img = await loadImage(dataUrl);
    const scale = Math.min(1, MAX_EDGE / Math.max(img.width, img.height));
    const w = Math.max(1, Math.round(img.width * scale));
    const h = Math.max(1, Math.round(img.height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    // Transparent source pixels must read as paper, not as black.
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(img, 0, 0, w, h);

    const luminance = luminanceMap(ctx.getImageData(0, 0, w, h), w, h);
    const background = backgroundMap(canvas, w, h);

    let mask = new Uint8Array(w * h);
    let ink = 0;
    for (let i = 0; i < mask.length; i++) {
        if (background[i] - luminance[i] >= MIN_INK_CONTRAST) {
            mask[i] = 1;
            ink++;
        }
    }

    if (ink < MIN_INK_PIXELS || ink / (w * h) > MAX_INK_COVERAGE) {
        throw new Error('no signature found');
    }

    mask = dropBackgroundShapes(despeckle(mask, w, h), w, h);
    if (!mask) throw new Error('no signature found');

    return render(mask, w, h);
}

function luminanceMap(imageData, w, h) {
    const px = imageData.data;
    const out = new Uint8Array(w * h);
    for (let i = 0; i < out.length; i++) {
        const p = i * 4;
        out[i] = (0.299 * px[p] + 0.587 * px[p + 1] + 0.114 * px[p + 2]) | 0;
    }
    return out;
}

/**
 * A smooth estimate of the paper behind every pixel: the image shrunk until
 * strokes dissolve into their surroundings, then stretched back. The browser
 * does the averaging in its own scaler.
 */
function backgroundMap(source, w, h) {
    const sw = Math.max(1, Math.floor(w / BACKGROUND_WINDOW_DIVISOR));
    const sh = Math.max(1, Math.floor(h / BACKGROUND_WINDOW_DIVISOR));

    const small = document.createElement('canvas');
    small.width = sw;
    small.height = sh;
    const sctx = small.getContext('2d');
    sctx.imageSmoothingEnabled = true;
    sctx.drawImage(source, 0, 0, sw, sh);

    const big = document.createElement('canvas');
    big.width = w;
    big.height = h;
    const bctx = big.getContext('2d', { willReadFrequently: true });
    bctx.imageSmoothingEnabled = true;
    bctx.drawImage(small, 0, 0, w, h);

    return luminanceMap(bctx.getImageData(0, 0, w, h), w, h);
}

/** Drop ink pixels with fewer than two ink neighbours (sensor/JPEG noise). */
function despeckle(mask, w, h) {
    const out = new Uint8Array(mask.length);
    for (let i = 0; i < mask.length; i++) {
        if (!mask[i]) continue;
        const x = i % w;
        const y = (i / w) | 0;
        let n = 0;
        for (let dy = -1; dy <= 1; dy++) {
            const ny = y + dy;
            if (ny < 0 || ny >= h) continue;
            for (let dx = -1; dx <= 1; dx++) {
                const nx = x + dx;
                if ((dx === 0 && dy === 0) || nx < 0 || nx >= w) continue;
                if (mask[ny * w + nx]) n++;
            }
        }
        if (n >= 2) out[i] = 1;
    }
    return out;
}

/**
 * Keep only connected shapes that sit wholly inside the frame and are not a
 * fraction of the largest one. Falls back to keeping everything when that would
 * leave nothing — a tight crop where the signature really does touch the edge.
 */
function dropBackgroundShapes(mask, w, h) {
    const work = Uint8Array.from(mask);
    const shapes = [];
    let largest = 0;

    for (let start = 0; start < work.length; start++) {
        if (!work[start]) continue;

        const queue = [start];
        work[start] = 0;
        const pixels = [];
        let border = false;

        for (let head = 0; head < queue.length; head++) {
            const index = queue[head];
            pixels.push(index);
            const x = index % w;
            const y = (index / w) | 0;
            if (x === 0 || y === 0 || x === w - 1 || y === h - 1) border = true;

            for (let dy = -1; dy <= 1; dy++) {
                const ny = y + dy;
                if (ny < 0 || ny >= h) continue;
                for (let dx = -1; dx <= 1; dx++) {
                    const nx = x + dx;
                    if (nx < 0 || nx >= w) continue;
                    const n = ny * w + nx;
                    if (work[n]) {
                        work[n] = 0;
                        queue.push(n);
                    }
                }
            }
        }

        largest = Math.max(largest, pixels.length);
        shapes.push({ pixels, border });
    }

    const minimum = Math.max(MIN_SHAPE_PIXELS, Math.floor(largest * MIN_SHAPE_RATIO));

    for (const allowBorder of [false, true]) {
        const out = new Uint8Array(mask.length);
        let kept = 0;
        for (const shape of shapes) {
            if (shape.pixels.length < minimum) continue;
            if (shape.border && !allowBorder) continue;
            for (const index of shape.pixels) out[index] = 1;
            kept += shape.pixels.length;
        }
        if (kept >= MIN_INK_PIXELS) return out;
    }

    return null;
}

/** Crop to the ink and paint it onto transparency. */
function render(mask, w, h) {
    let minX = w, minY = h, maxX = -1, maxY = -1;
    for (let i = 0; i < mask.length; i++) {
        if (!mask[i]) continue;
        const x = i % w;
        const y = (i / w) | 0;
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
    }

    const x1 = Math.max(0, minX - CROP_PADDING);
    const y1 = Math.max(0, minY - CROP_PADDING);
    const x2 = Math.min(w - 1, maxX + CROP_PADDING);
    const y2 = Math.min(h - 1, maxY + CROP_PADDING);
    const cw = x2 - x1 + 1;
    const ch = y2 - y1 + 1;

    const out = document.createElement('canvas');
    out.width = cw;
    out.height = ch;
    const octx = out.getContext('2d');
    const image = octx.createImageData(cw, ch);

    for (let y = 0; y < ch; y++) {
        for (let x = 0; x < cw; x++) {
            if (!mask[(y1 + y) * w + x1 + x]) continue;
            const p = (y * cw + x) * 4;
            image.data[p] = INK[0];
            image.data[p + 1] = INK[1];
            image.data[p + 2] = INK[2];
            image.data[p + 3] = 255;
        }
    }

    octx.putImageData(image, 0, 0);
    return out.toDataURL('image/png');
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}
