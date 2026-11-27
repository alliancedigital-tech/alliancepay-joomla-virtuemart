#!/bin/bash
# Builds the installable AlliancePay package (payment plugin + subscriptions module) from the repository.
#
# Expected layout (MODULES_DIR defaults to this script's directory, i.e. <repo>/package):
#   <repo>/                                   -> plg_vmpayment_alliancepay  (the payment plugin itself)
#   $MODULES_DIR/plugins/vmcustom/alliancepay_subscription/  -> plg_vmcustom_alliancepay_subscription
#   $MODULES_DIR/plugins/task/alliancepay/                   -> plg_task_alliancepay
#   $MODULES_DIR/component/                                   -> com_alliancepay
#         alliancepay.xml   (component manifest)
#         admin/            (administrator part: access.xml, language, services, src, tmpl)
#         site/             (site part: language, services, src, tmpl)
#   $MODULES_DIR/pkg_alliancepay.xml                          -> package manifest
#
# Environment:
#   MODULES_DIR    where the module folders above live            (default: this script's directory)
#   DIST           output directory                                (default: $MODULES_DIR/dist)
#   SKIP_COMPOSER  1 = do not run "composer install --no-dev"      (default: 0; only for quick local builds)
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$(cd "$HERE/.." && pwd)"
MODULES_DIR="$(cd "${MODULES_DIR:-$HERE}" && pwd)"
DIST="${DIST:-$MODULES_DIR/dist}"

VMCUSTOM_DIR="$MODULES_DIR/plugins/vmcustom/alliancepay_subscription"
FISCAL_DIR="$MODULES_DIR/plugins/vmcustom/alliancepay_fiscal"
TASK_DIR="$MODULES_DIR/plugins/task/alliancepay"
COMPONENT_DIR="$MODULES_DIR/component"
PKG_MANIFEST="$MODULES_DIR/pkg_alliancepay.xml"

fail() { echo "ERROR: $*" >&2; exit 1; }

[ -f "$PLUGIN_DIR/alliancepay.xml" ]        || fail "payment plugin manifest not found: $PLUGIN_DIR/alliancepay.xml"
[ -f "$PLUGIN_DIR/script.php" ]             || fail "installer script not found: $PLUGIN_DIR/script.php (the manifest <scriptfile>)"
[ -f "$VMCUSTOM_DIR/alliancepay_subscription.xml" ] || fail "vmcustom plugin not found in $VMCUSTOM_DIR"
[ -f "$FISCAL_DIR/alliancepay_fiscal.xml" ]       || fail "vmcustom fiscal plugin not found in $FISCAL_DIR"
[ -f "$TASK_DIR/alliancepay.xml" ]          || fail "task plugin not found in $TASK_DIR"
[ -f "$COMPONENT_DIR/alliancepay.xml" ]     || fail "component manifest not found: $COMPONENT_DIR/alliancepay.xml"
[ -d "$COMPONENT_DIR/admin" ]               || fail "component admin folder not found: $COMPONENT_DIR/admin"
[ -d "$COMPONENT_DIR/site" ]                || fail "component site folder not found: $COMPONENT_DIR/site"
[ -f "$PKG_MANIFEST" ]                      || fail "package manifest not found: $PKG_MANIFEST"

xml_version() { sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$1" | head -1; }

VERSION="$(xml_version "$PKG_MANIFEST")"
[ -n "$VERSION" ] || fail "cannot read <version> from $PKG_MANIFEST"

# All modules ship together, so their versions must agree with the package.
for manifest in "$PLUGIN_DIR/alliancepay.xml" "$VMCUSTOM_DIR/alliancepay_subscription.xml" "$FISCAL_DIR/alliancepay_fiscal.xml" "$TASK_DIR/alliancepay.xml" "$COMPONENT_DIR/alliancepay.xml"; do
  found="$(xml_version "$manifest")"
  [ "$found" = "$VERSION" ] || fail "version mismatch: $manifest has $found, package has $VERSION"
done

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "Building AlliancePay package $VERSION"
echo "  plugin:  $PLUGIN_DIR"
echo "  modules: $MODULES_DIR"
rm -rf "$DIST"; mkdir -p "$DIST"

echo "1/5 vmpayment plugin"
if [ "${SKIP_COMPOSER:-0}" != "1" ]; then
  ( cd "$PLUGIN_DIR" && composer install --no-dev --optimize-autoloader --quiet )
else
  echo "    (composer skipped: the vendor/ folder is packed as it is)"
fi
[ -f "$PLUGIN_DIR/vendor/autoload.php" ] || fail "vendor/autoload.php is missing: run composer install"
( cd "$PLUGIN_DIR" && zip -qr "$STAGE/plg_vmpayment_alliancepay.zip" . \
    -x ".git/*" ".gitignore" ".github/*" ".idea/*" ".vscode/*" ".env*" "build.sh" "composer.lock" "package/*" "screenshots/*" "tests/*" "phpunit.xml.dist" ".phpunit.cache/*" "fiscalization-prompt.md" "FISCALIZATION.md" "Joomla.md" "*.zip" "*.DS_Store" "*Thumbs.db" "*.swp" "*~" "*.log" )
# script.php syncs the DB schema on install/update, so the package must carry it (and the manifest must name it).
PLUGIN_ZIP_LIST="$(unzip -l "$STAGE/plg_vmpayment_alliancepay.zip")"
grep -qE '[[:space:]]script\.php$' <<< "$PLUGIN_ZIP_LIST" || fail "script.php is missing from plg_vmpayment_alliancepay.zip"
grep -q '<scriptfile>script.php</scriptfile>' "$PLUGIN_DIR/alliancepay.xml" || fail "alliancepay.xml does not declare <scriptfile>script.php</scriptfile>"

echo "2/5 vmcustom plugin"
( cd "$VMCUSTOM_DIR" && zip -qr "$STAGE/plg_vmcustom_alliancepay_subscription.zip" . -x "*.DS_Store" )
( cd "$FISCAL_DIR" && zip -qr "$STAGE/plg_vmcustom_alliancepay_fiscal.zip" . -x "*.DS_Store" )

echo "3/5 task plugin"
( cd "$TASK_DIR" && zip -qr "$STAGE/plg_task_alliancepay.zip" . -x "*.DS_Store" )

echo "4/5 component"
( cd "$COMPONENT_DIR" && zip -qr "$STAGE/com_alliancepay.zip" alliancepay.xml admin site -x "*.DS_Store" )

echo "5/5 package"
cp "$PKG_MANIFEST" "$STAGE/pkg_alliancepay.xml"
( cd "$STAGE" && zip -q "$DIST/pkg_alliancepay-$VERSION.zip" \
    pkg_alliancepay.xml \
    plg_vmpayment_alliancepay.zip \
    plg_vmcustom_alliancepay_subscription.zip \
    plg_vmcustom_alliancepay_fiscal.zip \
    plg_task_alliancepay.zip \
    com_alliancepay.zip )

echo "Done: $DIST/pkg_alliancepay-$VERSION.zip"
