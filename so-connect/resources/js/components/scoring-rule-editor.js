/**
 * Alpine component for the Scratch-like scoring trigger editor. Blockly is
 * heavy (~300 KB gz), so it is imported dynamically here — only the rule
 * editor page pays for it, never the main bundle.
 */
export function scoringRuleEditor(config) {
    let workspace = null; // Konva-style: keep non-reactive refs out of Alpine's proxy
    let BlocklyRef = null;
    let compileRef = null;
    let resizeObserver = null;

    return {
        loading: true,
        saving: false,
        error: '',
        enabled: config.enabled ?? true,

        async init() {
            try {
                const [Blockly, blocks] = await Promise.all([
                    import('blockly'),
                    import('../lib/scoring-blocks'),
                ]);
                BlocklyRef = Blockly;
                compileRef = blocks.compileWorkspace;

                blocks.defineScoringBlocks(Blockly, config.variables || {});

                // Reveal the container before injecting: Blockly measures the
                // target element on inject and renders an empty, zero-size
                // canvas if it is still display:none (x-show="!loading").
                this.loading = false;
                await this.$nextTick();

                workspace = Blockly.inject(this.$refs.blockly, {
                    toolbox: blocks.TOOLBOX,
                    renderer: 'zelos', // the Scratch-look renderer
                    trashcan: true,
                    zoom: { controls: true, wheel: false, startScale: 0.9 },
                });

                BlocklyRef.serialization.workspaces.load(
                    config.workspace && config.workspace.blocks ? config.workspace : blocks.STARTER_WORKSPACE,
                    workspace,
                );

                // Blockly measures the container once at inject time and renders
                // its SVG to that snapshot. When the flex/grid layout settles a
                // frame later the canvas is left too small (palette clipped) until
                // a window resize forces a recompute. Re-measure once now, and keep
                // the SVG in sync with the container via a ResizeObserver so it
                // always fills the parent regardless of window resizes.
                BlocklyRef.svgResize(workspace);
                resizeObserver = new ResizeObserver(() => {
                    if (workspace) BlocklyRef.svgResize(workspace);
                });
                resizeObserver.observe(this.$refs.blockly);
            } catch (e) {
                this.error = 'The block editor failed to load: ' + e.message;
            } finally {
                this.loading = false;
            }
        },

        destroy() {
            if (resizeObserver) {
                resizeObserver.disconnect();
                resizeObserver = null;
            }
            if (workspace) {
                workspace.dispose();
                workspace = null;
            }
        },

        async save() {
            if (!workspace || !compileRef) return;
            this.error = '';

            const compiled = compileRef(workspace);
            if (compiled.error) {
                this.error = compiled.error;
                return;
            }

            this.saving = true;
            try {
                const res = await fetch(config.saveUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        workspace: BlocklyRef.serialization.workspaces.save(workspace),
                        trigger: compiled.trigger,
                        enabled: this.enabled,
                    }),
                });
                const json = await res.json();
                if (res.ok && json.redirect) {
                    window.location.href = json.redirect;
                    return;
                }
                this.error = json.errors
                    ? Object.values(json.errors).flat()[0]
                    : (json.message || 'Save failed.');
            } catch (e) {
                this.error = 'Save failed.';
            } finally {
                this.saving = false;
            }
        },
    };
}
