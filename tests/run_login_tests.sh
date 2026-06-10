#!/usr/bin/env bash
# tests/run_login_tests.sh
# -----------------------------------------------------------------------------
# One-shot wrapper: boots the PHP built-in server, runs the regression test,
# tears the server down.  Assumes a MySQL DB is reachable per .env / .env.example.
# -----------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")/.."

PORT="${PORT:-8080}"
HOST="${HOST:-127.0.0.1}"
LOG=/tmp/kinas-server.log

if ! command -v php >/dev/null 2>&1; then
  echo "PHP not found. Install it first." >&2
  exit 2
fi

if ! php -m | grep -qi '^curl$'; then
  echo "PHP curl extension missing. Run: apt install php-curl" >&2
  exit 2
fi

# Make sure .env exists
if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
  echo "Created .env from .env.example — edit DB creds before re-running."
fi

echo "Starting PHP built-in server at http://$HOST:$PORT (log: $LOG)"
php -S "$HOST:$PORT" -t . >"$LOG" 2>&1 &
SERVER_PID=$!
cleanup() {
  kill "$SERVER_PID" 2>/dev/null || true
}
trap cleanup EXIT

# Give the server a moment to come up
for i in 1 2 3 4 5 6 7 8 9 10; do
  if curl -fsS "http://$HOST:$PORT/" >/dev/null 2>&1; then
    break
  fi
  sleep 0.3
done

echo "Running login regression test…"
php tests/login_regression.php "http://$HOST:$PORT" "${1:-}"
