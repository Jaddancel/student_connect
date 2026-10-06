# Document Server with the plugin panel made non-closable.
#
# The stock image renders two buttons in the docked plugin panel's header, and
# its ✕ destroys the plugin instance. We need the "Field tokens" palette to be
# permanently one click away, so the header carries a single button that
# collapses. There is no configuration flag for this — see
# patch-plugin-panel.sh for why patching the vendored JS is the only seam.
#
# Because the patch edits DS internals that carry no compatibility guarantee,
# the script asserts on its anchors and fails the build loudly rather than
# silently no-op'ing if a future image moves them.
FROM onlyoffice/documentserver:latest

COPY patch-plugin-panel.sh /usr/local/bin/patch-plugin-panel.sh

RUN chmod +x /usr/local/bin/patch-plugin-panel.sh \
    && /usr/local/bin/patch-plugin-panel.sh
