# TODO — Crop + straighten (4-corner perspective) step for ID-template editor

Branch: `wysiwyg-template-editor`. Scope = SuperAdmin template authoring only.
No controller/model/migration/route changes (existing `uploadImage`/`store`/`update` cover it).

## Tasks

- [x] 1. `resources/js/lib/perspective-warp.js` (new)
  - [x] `computeHomography(srcQuad, dstQuad)` — 8-DOF homography via 8×8 Gaussian elimination
  - [x] `warp(source, srcCorners, outW, outH) -> HTMLCanvasElement` — WebGL inverse-map fragment shader; dependency-free
- [x] 2. `resources/js/components/id-template-editor.js`
  - [x] add reactive `mode` ('crop'|'zones'), `aspectMode` ('id1'|'free'|'custom'), `customW`, `customH`, `outputLongEdge` (+ `canReCrop`)
  - [x] closure state: raw `<img>`, raw `File`, crop Konva stage, polygon Line + 4 corner Circles, preview rAF handle
  - [x] `uploadImage()` → enter crop mode instead of straight to zones (decode-then-crop; upload deferred to straighten/skip)
  - [x] `enterCropMode()` / `buildCropStage()` place 4 anchors at image corners + initial preview
  - [x] `onCornerChange()`(via anchor `dragmove`) / `onAspectChange()` recompute out size + repaint preview (rAF-coalesced)
  - [x] `targetOutputSize()` from aspect ratio (id1=85.6:53.98, free=quad edge avg, custom=W:H) + `outputLongEdge`
  - [x] `straighten()` — full-res warp → toBlob → File → existing upload route → zones mode
  - [x] `skipStraighten()` — use raw upload as-is → zones mode
  - [x] `reCrop()` — back to crop mode (warn zones reset)
  - [x] `init()` on existing template loads straight into zones mode
- [x] 3. `resources/views/pages/superadmin/id-templates/_form.blade.php`
  - [x] crop step block (`x-show="mode==='crop'"`): crop stage, aspect controls, long-edge, preview, Straighten/Skip buttons
  - [x] zone step block (`x-show="mode==='zones'"`): existing stage + panel + Re-crop button
- [x] 4. Build: `PATH=~/.nvm/versions/node/v24.16.0/bin:$PATH npm run build` clean
- [x] 5. Verify: `APP_ENV=testing DB_HOST=127.0.0.1 php artisan test --filter=IdTemplate` green (12 passed); re-seeded after (migrate:fresh --seed)
- [ ] 6. Manual click-path (documented below; WebGL not testable headless)

### Manual click-path to verify (SuperAdmin, requires a browser with WebGL)
1. `/superadmin/id-templates/create` → **Upload image** a slightly angled ID photo → lands in **crop** step.
2. Drag the four blue corners onto the card edges; the right-hand **Preview** repaints live and de-skewed.
3. Toggle aspect: *Standard ID card* vs *Free* vs *Custom* (W/H); change *Output long edge* → preview aspect updates.
4. **Straighten & continue** → straightened image uploads and the **zones** step opens over the corrected image.
   (Or **Skip & use as-is** → zones step over the raw upload.)
5. Draw zones, then **Re-crop** → confirm the "resets zones" prompt → returns to crop with the same raw image.
6. **Save template**, re-open via edit → loads straight into the zones step (no crop), zones overlay exactly.

## Notes
- Konva/WebGL objects live in the closure, never on the reactive Alpine object.
- Corners passed to `warp()` in source-image NATIVE px; output is straightened rect.
- Known limitation: scan-side photos are NOT straightened (future follow-up).
