/**
 * Dependency-free WebGL perspective (homography) warp.
 *
 * Given four source corners in an image and a target rectangle size, it renders
 * the quadrilateral into an axis-aligned rectangle — i.e. it "de-skews" an
 * angled photo of a flat object (an ID) into a straight, front-on crop.
 *
 * The work is done on the GPU: a full-screen quad is drawn over the output
 * canvas and the fragment shader inverse-maps each destination pixel back
 * through the homography to sample the source texture. Keeping this pure and
 * free of npm deps means it can be reused later for a scan-time straighten step.
 */

/**
 * Solve the 8-DOF homography mapping four source points to four destination
 * points. Returns a length-9 row-major 3x3 matrix H (h8 fixed at 1) such that
 * dst ≈ H · src (in homogeneous coords).
 *
 * @param {Array<[number,number]>} src four [x,y] source points
 * @param {Array<[number,number]>} dst four [x,y] destination points
 * @returns {number[]} row-major 3x3 (length 9)
 */
export function computeHomography(src, dst) {
    // Build the 8x8 linear system A·h = b for the unknowns
    // [h0..h7] (h8 = 1), two rows per point correspondence.
    const A = [];
    const b = [];
    for (let i = 0; i < 4; i++) {
        const [sx, sy] = src[i];
        const [dx, dy] = dst[i];
        A.push([sx, sy, 1, 0, 0, 0, -sx * dx, -sy * dx]);
        b.push(dx);
        A.push([0, 0, 0, sx, sy, 1, -sx * dy, -sy * dy]);
        b.push(dy);
    }

    const h = solveLinear(A, b); // length 8
    return [h[0], h[1], h[2], h[3], h[4], h[5], h[6], h[7], 1];
}

/**
 * Gaussian elimination with partial pivoting for a square system A·x = b.
 * Mutates copies, returns x. Falls back to a near-identity if singular.
 *
 * @param {number[][]} A n×n
 * @param {number[]} b length n
 * @returns {number[]} length n
 */
function solveLinear(A, b) {
    const n = b.length;
    // Augmented matrix.
    const M = A.map((row, i) => [...row, b[i]]);

    for (let col = 0; col < n; col++) {
        // Partial pivot: find the largest-magnitude entry in this column.
        let pivot = col;
        for (let r = col + 1; r < n; r++) {
            if (Math.abs(M[r][col]) > Math.abs(M[pivot][col])) pivot = r;
        }
        if (Math.abs(M[pivot][col]) < 1e-12) continue; // singular-ish, skip
        [M[col], M[pivot]] = [M[pivot], M[col]];

        // Normalize the pivot row.
        const pv = M[col][col];
        for (let c = col; c <= n; c++) M[col][c] /= pv;

        // Eliminate this column from all other rows.
        for (let r = 0; r < n; r++) {
            if (r === col) continue;
            const factor = M[r][col];
            if (factor === 0) continue;
            for (let c = col; c <= n; c++) M[r][c] -= factor * M[col][c];
        }
    }

    return M.map((row) => row[n]);
}

/** Invert a row-major 3x3 matrix; returns row-major length-9. */
function invert3x3(m) {
    const [a, b, c, d, e, f, g, h, i] = m;
    const A = e * i - f * h;
    const B = -(d * i - f * g);
    const C = d * h - e * g;
    const det = a * A + b * B + c * C;
    if (Math.abs(det) < 1e-12) {
        return [1, 0, 0, 0, 1, 0, 0, 0, 1];
    }
    const invDet = 1 / det;
    return [
        A * invDet,
        (c * h - b * i) * invDet,
        (b * f - c * e) * invDet,
        B * invDet,
        (a * i - c * g) * invDet,
        (c * d - a * f) * invDet,
        C * invDet,
        (b * g - a * h) * invDet,
        (a * e - b * d) * invDet,
    ];
}

const VERTEX_SRC = `
attribute vec2 a_pos;
varying vec2 v_dst;
void main() {
    // a_pos is a clip-space quad covering the whole output.
    v_dst = (a_pos * 0.5 + 0.5);
    gl_Position = vec4(a_pos, 0.0, 1.0);
}`;

const FRAGMENT_SRC = `
precision highp float;
varying vec2 v_dst;
uniform sampler2D u_image;
uniform mat3 u_invH;    // dst(px) -> src(px) homography
uniform vec2 u_outSize; // output pixel size
uniform vec2 u_srcSize; // source texture pixel size
void main() {
    // v_dst is 0..1 over the output; y is flipped because texture space
    // has origin at bottom-left while our corner coords are top-left.
    vec2 dstPx = vec2(v_dst.x, 1.0 - v_dst.y) * u_outSize;
    vec3 srcH = u_invH * vec3(dstPx, 1.0);
    vec2 srcPx = srcH.xy / srcH.z;
    // With UNPACK_FLIP_Y_WEBGL = false the image's TOP row is at t = 0, so a
    // native pixel row maps straight to its texcoord — no vertical flip here
    // (flipping mirrors sampling across the whole source and scrambles the crop).
    vec2 uv = srcPx / u_srcSize;
    if (uv.x < 0.0 || uv.x > 1.0 || uv.y < 0.0 || uv.y > 1.0) {
        gl_FragColor = vec4(0.0, 0.0, 0.0, 1.0);
    } else {
        gl_FragColor = texture2D(u_image, uv);
    }
}`;

function compileShader(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        const log = gl.getShaderInfoLog(shader);
        gl.deleteShader(shader);
        throw new Error('Shader compile failed: ' + log);
    }
    return shader;
}

/**
 * Warp a source image/canvas so the quad defined by `srcCorners` fills an
 * `outWidth × outHeight` output canvas.
 *
 * @param {HTMLImageElement|HTMLCanvasElement} source decoded image or canvas
 * @param {Array<[number,number]>} srcCorners four points in SOURCE NATIVE px,
 *        ordered [top-left, top-right, bottom-right, bottom-left]
 * @param {number} outWidth  output width in px
 * @param {number} outHeight output height in px
 * @returns {HTMLCanvasElement} the straightened output canvas
 */
export function warp(source, srcCorners, outWidth, outHeight) {
    const srcW = source.naturalWidth || source.width;
    const srcH = source.naturalHeight || source.height;

    const out = document.createElement('canvas');
    out.width = Math.max(1, Math.round(outWidth));
    out.height = Math.max(1, Math.round(outHeight));

    const gl = out.getContext('webgl') || out.getContext('experimental-webgl');
    if (!gl) throw new Error('WebGL is not available in this browser.');

    // Homography from the destination rectangle (0,0)-(outW,outH) to the source
    // quad, so the shader can inverse-map each output pixel to the source.
    const dstRect = [
        [0, 0],
        [out.width, 0],
        [out.width, out.height],
        [0, out.height],
    ];
    const H = computeHomography(dstRect, srcCorners); // dst -> src

    const program = gl.createProgram();
    const vs = compileShader(gl, gl.VERTEX_SHADER, VERTEX_SRC);
    const fs = compileShader(gl, gl.FRAGMENT_SHADER, FRAGMENT_SRC);
    gl.attachShader(program, vs);
    gl.attachShader(program, fs);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        throw new Error('Program link failed: ' + gl.getProgramInfoLog(program));
    }
    gl.useProgram(program);

    // Full-screen quad (two triangles) in clip space.
    const quad = new Float32Array([
        -1, -1, 1, -1, -1, 1,
        -1, 1, 1, -1, 1, 1,
    ]);
    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, quad, gl.STATIC_DRAW);
    const posLoc = gl.getAttribLocation(program, 'a_pos');
    gl.enableVertexAttribArray(posLoc);
    gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 0, 0);

    // Upload the source as a texture.
    const texture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, source);

    // WebGL wants column-major mat3; transpose our row-major H.
    const invHColMajor = [
        H[0], H[3], H[6],
        H[1], H[4], H[7],
        H[2], H[5], H[8],
    ];

    gl.uniform1i(gl.getUniformLocation(program, 'u_image'), 0);
    gl.uniformMatrix3fv(gl.getUniformLocation(program, 'u_invH'), false, invHColMajor);
    gl.uniform2f(gl.getUniformLocation(program, 'u_outSize'), out.width, out.height);
    gl.uniform2f(gl.getUniformLocation(program, 'u_srcSize'), srcW, srcH);

    gl.viewport(0, 0, out.width, out.height);
    gl.clearColor(0, 0, 0, 1);
    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.drawArrays(gl.TRIANGLES, 0, 6);

    // Release GPU resources; the pixels now live in the canvas bitmap.
    gl.deleteTexture(texture);
    gl.deleteBuffer(buffer);
    gl.deleteShader(vs);
    gl.deleteShader(fs);
    gl.deleteProgram(program);

    return out;
}

// Exported for potential reuse/testing of the inverse mapping.
export { invert3x3 };
