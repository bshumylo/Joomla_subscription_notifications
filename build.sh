#!/usr/bin/env bash
# Builds the installable package zip into dist/.
set -euo pipefail
cd "$(dirname "$0")"

rm -rf dist
mkdir -p dist/packages

# Ship the licence text inside every extension, as the GPL requires.
for ext in mod_newsletter_subscription plg_content_new_article_notifications; do
  cp LICENSE "packages/$ext/LICENSE"
  (cd "packages/$ext" && zip -qr "../../dist/packages/$ext.zip" . -x "docker-logs/*" -x "tests/*")
  rm -f "packages/$ext/LICENSE"
done

cp pkg_subscription_and_notifications.xml pkg_script.php LICENSE dist/
VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' pkg_subscription_and_notifications.xml | head -1)
(cd dist && zip -qr "pkg_subscription_and_notifications-${VERSION}.zip" pkg_subscription_and_notifications.xml pkg_script.php LICENSE packages)

# Guard: all three manifests must carry the same version.
for m in packages/*/[a-z]*.xml; do
  MV=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$m" | head -1)
  [ "$MV" = "$VERSION" ] || { echo "Version mismatch: $m is $MV, package is $VERSION" >&2; exit 1; }
done

echo "Built dist/pkg_subscription_and_notifications-${VERSION}.zip"
