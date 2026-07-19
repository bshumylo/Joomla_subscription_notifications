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

# --- Git Bash (MSYS) support: disable path mangling; convert host paths explicitly ---
HOSTPATH() { printf '%s' "$1"; }
case "$(uname -s)" in
  MINGW*|MSYS*)
    export MSYS_NO_PATHCONV=1
    export MSYS2_ARG_CONV_EXCL='*'
    HOSTPATH() { cygpath -w "$1"; }
    ;;
esac

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
  docker build -t "${IMAGE}" -f "$(HOSTPATH "${SKILL_DIR}/docker/Dockerfile")" "$(HOSTPATH "${SKILL_DIR}/docker")"

# Remove leftover test containers (e.g. a previous --keep run holding port 8080)
OLD=$(docker ps -aq --filter "name=joomla-test")
[ -n "${OLD}" ] && docker rm -f ${OLD} >/dev/null 2>&1

echo "== Start container ${CONTAINER}"
docker run -d --name "${CONTAINER}" \
  -p 8080:80 \
  -v "$(HOSTPATH "${EXT_PATH}"):/ext:ro" \
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
echo "Joomla version: $(docker exec "${CONTAINER}" php -r 'define("_JEXEC", 1); require "/var/www/joomla/libraries/src/Version.php"; echo (new Joomla\CMS\Version)->getShortVersion();' 2>/dev/null)"

echo "== Install the extension"
docker cp "$(HOSTPATH "${SKILL_DIR}/docker/zipext.php")" "${CONTAINER}:/tmp/zipext.php"
docker exec "${CONTAINER}" bash -c '
  php /tmp/zipext.php
  php /var/www/joomla/cli/joomla.php extension:install --path=/tmp/ext.zip
' > "${LOGS_DIR}/install.log" 2>&1
if grep -qiE "error|failed|exception" "${LOGS_DIR}/install.log"; then
  echo "INSTALL FAILED — see docker-logs/install.log"; FAIL=1
else
  echo "install OK"
fi
cat "${LOGS_DIR}/install.log"

echo "== PHPStan (against real Joomla classes)"
docker cp "$(HOSTPATH "${SKILL_DIR}/docker/phpstan-bootstrap.php")" "${CONTAINER}:/tmp/phpstan-bootstrap.php"
docker exec "${CONTAINER}" bash -c '
  export PATH="$PATH:$(composer global config bin-dir --absolute 2>/dev/null):/root/.composer/vendor/bin:/root/.config/composer/vendor/bin"
  cd /var/www/joomla
  SRC=$(grep -rl "namespace" /ext --include="*.php" | head -1 >/dev/null && echo /ext || echo /ext)
  phpstan analyse /ext/src /ext/services --level=5 --autoload-file=/tmp/phpstan-bootstrap.php 2>&1 || true
' > "${LOGS_DIR}/phpstan.log" 2>&1 || true
tail -20 "${LOGS_DIR}/phpstan.log"

if [ -d "${EXT_PATH}/tests" ]; then
  echo "== PHPUnit"
  docker exec "${CONTAINER}" bash -c '
    export PATH="$PATH:$(composer global config bin-dir --absolute 2>/dev/null):/root/.composer/vendor/bin:/root/.config/composer/vendor/bin"
    cd /ext && JOOMLA_ROOT=/var/www/joomla phpunit --colors=never tests 2>&1
  ' > "${LOGS_DIR}/phpunit.log" 2>&1
  if grep -qE "FAILURES|ERRORS" "${LOGS_DIR}/phpunit.log"; then
    echo "TESTS FAILED — see docker-logs/phpunit.log"; FAIL=1
  fi
  tail -20 "${LOGS_DIR}/phpunit.log"
fi

echo "== Export Joomla / Apache logs"
docker exec "${CONTAINER}" bash -c 'cat /var/www/joomla/administrator/logs/*.php 2>/dev/null' \
  > "${LOGS_DIR}/joomla-error.log" 2>/dev/null || true
docker cp "${CONTAINER}:/var/log/joomla/apache-error.log" "$(HOSTPATH "${LOGS_DIR}")" 2>/dev/null || true

if [ "${KEEP}" = "--keep" ]; then
  echo "Container kept running: ${CONTAINER}  (site: http://localhost:8080, admin/admin12345678)"
else
  docker stop "${CONTAINER}" >/dev/null && docker rm "${CONTAINER}" >/dev/null
fi

echo
echo "Logs in: ${LOGS_DIR}"
exit ${FAIL}
