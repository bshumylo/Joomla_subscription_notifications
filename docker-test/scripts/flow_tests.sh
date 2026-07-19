#!/bin/bash
# Functional flow tests over real HTTP (session + CSRF token), like a browser.
set -u
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/../.."
C=$(docker ps -q --filter "name=joomla-test" | head -1)
{
  docker exec "$C" bash -c '
    cd /tmp
    step() { echo; echo "== $1"; }
    post() { # $1=data-without-token
      TOKEN=$(curl -s -b cj.txt -c cj.txt http://localhost/ | grep -oE "name=\"[0-9a-f]{32}\" value=\"1\"" | head -1 | grep -oE "[0-9a-f]{32}")
      curl -s -b cj.txt -c cj.txt -L -X POST -d "$1&$TOKEN=1" http://localhost/ \
        | grep -oE "class=\"alert alert-[a-z]+\">[^<]*" | head -2
    }
    rm -f cj.txt

    step "1 subscribe duplicate (flow-test already in DB) -> expect already-subscribed info"
    post "subscribe=1&email=flow-test@example.com"

    step "2 subscribe new address -> expect success"
    post "subscribe=1&email=second@example.com"

    step "3 invalid email with valid token -> expect invalid email error"
    post "subscribe=1&email=not-an-email"

    step "4 unsubscribe existing -> expect success"
    post "unsubscribe=1&email=second@example.com"

    step "5 unsubscribe non-subscribed -> expect not-subscribed info"
    post "unsubscribe=1&email=nobody@example.com"
  '
  echo; echo "== final DB state"
  docker exec "$C" mariadb -ujoomla -pjoomla joomla -e "SELECT id,email FROM jos_newsletter_subscribers;"
} > flow-tests.log 2>&1
echo "FLOWTESTS DONE $(date)" >> test-run-status.txt
