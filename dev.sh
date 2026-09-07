#!/usr/bin/env bash
# Run any command against this repo inside the pest-dev container.
#   podman build -t pest-dev .
#   ./dev.sh composer install
#   ./dev.sh composer test
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

TTY=()
[ -t 0 ] && TTY=(-it)

exec podman run --rm "${TTY[@]}" \
  --security-opt label=disable \
  -v "$DIR":/app \
  -w /app \
  -e XDEBUG_MODE=off \
  -e COMPOSER_ALLOW_SUPERUSER=1 \
  pest-dev "$@"
