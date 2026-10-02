# Browser-test targets for this module, sourced by the shared runner
# (~/Sites/0_ss-mods-maintenance/tools/browser/run.sh) and by .github/workflows/browser-tests.yml.
# Plain bash assignments only. CI tests only the targets with an empty SS<n>_SRC_REF (= this
# checkout): on this branch that is both.
# Ports are assigned in ~/Sites/0_ss-mods-maintenance/tools/browser/PORTS.md; take new ones there.

BROWSER_PACKAGE="restruct/silverstripe-asset_icons"
BROWSER_TARGETS="ss5 ss6"

# This branch (main, 2.4.x) requires asset-admin ^2 || ^3, so it serves BOTH majors (empty ref =
# the checkout the runner was given). Older lines are tags only, no maintained branch.
SS5_RECIPE="^5"
SS5_PHP="8.3"
SS5_PORT="8863"
SS5_SRC_REF=""

SS6_RECIPE="^6"
SS6_PHP="8.3"
SS6_PORT="8864"
SS6_SRC_REF=""
