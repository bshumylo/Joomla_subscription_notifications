#!/usr/bin/env bash
# Builds the installable package zip into dist/.
set -euo pipefail
cd "$(dirname "$0")"

rm -rf dist
mkdir -p dist/packages

(cd packages/mod_newsletter_subscription && zip -qr ../../dist/packages/mod_newsletter_subscription.zip . -x "docker-logs/*" -x "tests/*")
(cd packages/plg_content_new_article_notifications && zip -qr ../../dist/packages/plg_content_new_article_notifications.zip . -x "docker-logs/*" -x "tests/*")

cp pkg_subscription_and_notifications.xml pkg_script.php dist/
VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' pkg_subscription_and_notifications.xml | head -1)
(cd dist && zip -qr "pkg_subscription_and_notifications-${VERSION}.zip" pkg_subscription_and_notifications.xml pkg_script.php packages)

echo "Built dist/pkg_subscription_and_notifications-${VERSION}.zip"
