#!/bin/bash
# Orchestrates the full Docker test run for both extensions.
# Run from repo root (RUN_TESTS.bat does this). Writes progress to
# test-run-status.txt so the run can be monitored externally.
set -u
export MSYS_NO_PATHCONV=1

cd "$(dirname "$0")/../.."
STATUS="test-run-status.txt"
echo "RUNNING started $(date)" > "$STATUS"

echo "STEP plugin $(date)" >> "$STATUS"
bash docker-test/scripts/test_docker_win.sh packages/plg_content_new_article_notifications
PLG=$?
echo "PLUGIN exit=$PLG" >> "$STATUS"

echo "STEP module $(date)" >> "$STATUS"
bash docker-test/scripts/test_docker_win.sh packages/mod_newsletter_subscription --keep
MOD=$?
echo "MODULE exit=$MOD" >> "$STATUS"

# Publish the module on the kept site so it can be tested in a browser.
C=$(docker ps -q --filter "name=joomla-test" | head -1)
if [ -n "$C" ]; then
  docker exec "$C" mariadb -ujoomla -pjoomla joomla -e "
    UPDATE jos_modules SET published=1, position='sidebar-right', access=1, showtitle=1, title='Newsletter' WHERE module='mod_newsletter_subscription';
    INSERT IGNORE INTO jos_modules_menu (moduleid, menuid) SELECT id, 0 FROM jos_modules WHERE module='mod_newsletter_subscription';
  " && echo "MODULE published for browser test" >> "$STATUS"
fi

echo "DONE plg=$PLG mod=$MOD $(date)" >> "$STATUS"
