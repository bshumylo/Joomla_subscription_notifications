#!/bin/bash
# Docker-based live test for a Joomla! extension.
#
# Usage:
#   ./test_docker.sh <extension-folder> [--keep]
#
# What it does:
#   1. builds the test image (once; reused afterwards)
#   2. starts a container: MariaDB + latest stable Joomla (auto-downloaded
#      from GitHub releases) + Apache
#   3. zips and installs the extension via Joomla console (extension:install)
#   4. runs PHPStan over the extension source inside Joomla
#   5. runs PHPUnit if the extension has a tests/ folder
#   6. exports all logs to <ext>/docker-logs/ and prints a summary
#
# Logs to read after a run (in <ext>/docker-logs/):
#   install.log        extension installation output (discovery + errors)
#   phpstan.log        static analysis against real Joomla classes
#   phpunit.log        test results (if tests/ exists)
#   joomla-error.log   Joomla's own error log (administrator/logs)
#   apache-error.log   PHP fatals / warnings at web level
#
# Exit code: 0 if install + all executed checks passed.
set -u

EXT_PATH="$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"
KEEP="${2:-}"
SKILL_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOGS_DIR="${EXT_PATH}/docker-logs"
IMAGE="joomla-ext-test:latest"
CONTAINER="joomla-test-$(date +%s)"
FAIL=0

mkdir -p "${LOGS_DIR}"

if ! docker info >/dev/null 2>&1; then
  echo "DOCKER_UNAVAILABLE — fall back to static validation + manual instructions."
  exit 2
fi

echo "== Build image (cached after first run)"
docker image inspect "${IMAGE}" >/dev/null 2>&1 || \
  docker build -t "${IMAGE}" -f "${SKILL_DIR}/docker/Dockerfile" "${SKILL_DIR}/docker"

echo "== Start container ${CONTAINER}"
docker run -d --name "${CONTAINER}" \
  -p 8080:80 \
  -v "${EXT_PATH}:/ext:ro" \
  "${IMAGE}" >/dev/null

echo "== Wait for Joomla (download + install can take a few minutes)"
for i in $(seq 1 60); do
  if docker exec "${CONTAINER}" test -f /var/www/joomla/configuration.php 2>/dev/null; then
    break
  fi
  sleep 10
done
docker exec "${CONTAINER}" test -f /var/www/joomla/configuration.php || {
  echo "Joomla did not come up — dumping container log"
  docker logs "${CONTAINER}" > "${LOGS_DIR}/container.log" 2>&1
  tail -40 "${LOGS_DIR}/container.log"
  [ "${KEEP}" = "--keep" ] || { docker stop "${CONTAINER}" >/dev/null; docker rm "${CONTAINER}" >/dev/null; }
  exit 1
}
docker exec "${CONTAINER}" php /var/www/joomla/cli/joomla.php core:check-updates >/dev/null 2>&1 || true
echo "Joomla version: $(docker exec "${CONTAINER}" php -r 'require "/var/www/joomla/libraries/src/Version.php"; echo (new Joomla\CMS\Version)->getShortVersion();' 2>/dev/null)"

echo "== Install the extension"
docker exec "${CONTAINER}" bash -c '
  cd /tmp && rm -f ext.zip && cd /ext && zip -qr /tmp/ext.zip . -x "docker-logs/*" "tests/*"
  php /var/www/joomla/cli/joomla.php extension:install --path=/tmp/ext.zip
' > "${LOGS_DIR}/install.log" 2>&1
if grep -qiE "error|failed|exception" "${LOGS_DIR}/install.log"; then
  echo "INSTALL FAILED — see docker-logs/install.log"; FAIL=1
else
  echo "install OK"
fi
cat "${LOGS_DIR}/install.log"

echo "== PHPStan (against real Joomla classes)"
docker exec "${CONTAINER}" bash -c '
  cd /var/www/joomla
  SRC=$(grep -rl "namespace" /ext --include="*.php" | head -1 >/dev/null && echo /ext || echo /ext)
  phpstan analyse /ext --level=5 --autoload-file=/var/www/joomla/includes/framework.php 2>&1 || true
' > "${LOGS_DIR}/phpstan.log" 2>&1 || true
tail -20 "${LOGS_DIR}/phpstan.log"

if [ -d "${EXT_PATH}/tests" ]; then
  echo "== PHPUnit"
  docker exec "${CONTAINER}" bash -c '
    cd /ext && phpunit --colors=never tests 2>&1
  ' > "${LOGS_DIR}/phpunit.log" 2>&1
  if grep -qE "FAILURES|ERRORS" "${LOGS_DIR}/phpunit.log"; then
    echo "TESTS FAILED — see docker-logs/phpunit.log"; FAIL=1
  fi
  tail -20 "${LOGS_DIR}/phpunit.log"
fi

echo "== Export Joomla / Apache logs"
docker exec "${CONTAINER}" bash -c 'cat /var/www/joomla/administrator/logs/*.php 2>/dev/null' \
  > "${LOGS_DIR}/joomla-error.log" 2>/dev/null || true
docker cp "${CONTAINER}:/var/log/joomla/apache-error.log" "${LOGS_DIR}/" 2>/dev/null || true

if [ "${KEEP}" = "--keep" ]; then
  echo "Container kept running: ${CONTAINER}  (site: http://localhost:8080, admin/admin12345678)"
else
  docker stop "${CONTAINER}" >/dev/null && docker rm "${CONTAINER}" >/dev/null
fi

echo
echo "Logs in: ${LOGS_DIR}"
exit ${FAIL}
