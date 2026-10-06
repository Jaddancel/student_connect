#!/bin/sh
#
# Make the docked plugin panel non-closable: its header keeps a single button,
# and that button collapses the panel instead of destroying the plugin.
#
# Why this exists
# ---------------
# The "Field tokens" palette is a Document Server plugin, because Community
# Edition has no Automation API and a plugin is the only channel that can insert
# text into the open document (see FormPrintTemplateController).
#
# By default OnlyOffice draws TWO buttons in the docked panel header — "Collapse
# plugin" (⇤) and "Close plugin" (✕) — and ✕ tears the plugin instance down, so
# the palette can only be recovered from the Plugins ribbon tab. We want the
# palette to always be one click away, so the single header button must collapse.
#
# There is no config flag for any of this: the button set is decided by a JS
# conditional inside the editor bundle, and the ✕ handler is hard-coded to
# destroy. The panel is a cross-origin iframe, so neither our plugin nor our host
# page can reach it at runtime. Patching the vendored JS is the only seam there
# is — which also means this must be re-checked whenever the DS image is bumped,
# since it edits internals that carry no compatibility guarantee.
#
# Two gotchas this script handles, both of which silently produce "nothing
# changed" if you miss them:
#   1. nginx serves pre-built .gz sidecars (gzip_static) in preference to the
#      .js files, regardless of mtime — the .gz must be regenerated.
#   2. Assets are served under a hashed path with `Cache-Control: immutable`
#      for a year, so browsers never revalidate. documentserver-flush-cache.sh
#      mints a new hash, which changes every asset URL and misses stale caches.
#      (api.js, which carries the hash, is itself sent no-store.)
#
# Run inside the documentserver container. Idempotent.
#
set -e

WEBAPPS=/var/www/onlyoffice/documentserver/web-apps/apps
PANEL_VIEWS="$WEBAPPS/common/main/lib/view/PluginPanel.js $WEBAPPS/documenteditor/main/code.js"
CONTROLLER="$WEBAPPS/common/main/lib/controller/Plugins.js"

python3 - "$CONTROLLER" $PANEL_VIEWS <<'PY'
import sys

controller, views = sys.argv[1], sys.argv[2:]

# 1. Stop the panel from instantiating the second ("Collapse plugin") button.
#    The header template always emits both <div>s; skipping the Button here
#    leaves .plugin-hide as an empty 0-width div.
VIEW_FROM = ("            if (this.sideMenuButton) {\n"
             "                this.pluginHide = new Common.UI.Button({")
VIEW_TO = ("            if (false && this.sideMenuButton) { // so-connect:single-button-header (✕ collapses, see onToolClose)\n"
           "                this.pluginHide = new Common.UI.Button({")
VIEW_MARK = "so-connect:single-button-header"

# 2. Repoint ✕ at the collapse behaviour. onToolHide() toggles the side-rail
#    button, which hides the panel while leaving the plugin iframe alive, so
#    reopening from the rail restores it instantly with its state intact.
#    The original called asc_pluginButtonClick(-1, ...), which destroys it.
CTRL_FROM = ("        onToolClose: function(panel) {\n"
             "            this.api.asc_pluginButtonClick(-1, panel && panel._state.insidePlugin, panel && panel.frameId);\n"
             "        },")
CTRL_TO = ("        onToolClose: function(panel) {\n"
           "            // so-connect:collapse-on-close — ✕ collapses (as onToolHide did) rather than destroying the plugin.\n"
           "            panel && panel.sideMenuButton && panel.sideMenuButton.click();\n"
           "        },")
CTRL_MARK = "so-connect:collapse-on-close"


def patch(path, old, new, marker, label):
    with open(path) as fh:
        body = fh.read()
    # Detect via a stable marker, not the full replacement text, so that
    # cosmetic edits to the patch don't make an applied patch look unapplied.
    if marker in body:
        print("  already patched: %s (%s)" % (path, label))
        return False
    if body.count(old) != 1:
        raise SystemExit(
            "ABORT: %s — expected exactly 1 match for the %s anchor, found %d.\n"
            "The OnlyOffice image has probably changed; re-derive the patch."
            % (path, label, body.count(old)))
    with open(path, "w") as fh:
        fh.write(body.replace(old, new))
    print("  patched: %s (%s)" % (path, label))
    return True


changed = False
for path in views:
    changed |= patch(path, VIEW_FROM, VIEW_TO, VIEW_MARK, "hide-button")
changed |= patch(controller, CTRL_FROM, CTRL_TO, CTRL_MARK, "close-handler")
sys.exit(0 if changed else 100)
PY
rc=$?

if [ "$rc" -eq 100 ]; then
    echo "Nothing to do — already patched."
    exit 0
fi

# nginx prefers the .gz sidecar; a stale one silently serves the unpatched code.
echo "Regenerating .gz sidecars…"
for f in $CONTROLLER $PANEL_VIEWS; do
    gzip -kf "$f"
done

# Mint a new asset hash so browsers holding the immutable-cached old bundles
# request fresh URLs. Reload nginx only if it is actually running (it is not
# during a docker build).
echo "Flushing asset cache…"
if service nginx status >/dev/null 2>&1; then
    documentserver-flush-cache.sh
else
    documentserver-flush-cache.sh -r false
fi

echo "Done. The Field tokens panel now has a single ✕ that collapses it."
