#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ -f "${ROOT_DIR}/.env" ]; then
  # shellcheck disable=SC1091
  set -a
  # shellcheck source=/dev/null
  source "${ROOT_DIR}/.env"
  set +a
fi

HOST_HTTP_PORT="${HOST_HTTP_PORT:-49200}"
COMPOSE_CMD=${COMPOSE_CMD:-"docker compose"}
APP_CONTAINER_NAME=${APP_CONTAINER_NAME:-"php_app"}

echo "Starting containers (detached mode)…"
${COMPOSE_CMD} up -d --build

echo "Waiting for ${APP_CONTAINER_NAME} to report healthy status…"
attempt=0
max_attempts=${HEALTHCHECK_MAX_ATTEMPTS:-60}
sleep_seconds=${HEALTHCHECK_INTERVAL_SECONDS:-2}

while true; do
  status="$(
    docker inspect \
      --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' \
      "${APP_CONTAINER_NAME}" 2>/dev/null || echo "starting"
  )"

  if [ "${status}" = "healthy" ] || [ "${status}" = "running" ]; then
    break
  fi

  if [ "${status}" = "exited" ] || [ "${status}" = "dead" ]; then
    echo "Container ${APP_CONTAINER_NAME} exited unexpectedly. Check logs with '${COMPOSE_CMD} logs ${APP_CONTAINER_NAME}'." >&2
    exit 1
  fi

  if [ "${attempt}" -ge "${max_attempts}" ]; then
    echo "Timed out waiting for ${APP_CONTAINER_NAME} to become healthy." >&2
    exit 1
  fi

  attempt=$((attempt + 1))
  sleep "${sleep_seconds}"
done

HOMEPAGE_URL="http://localhost:${HOST_HTTP_PORT}"

echo "Opening ${HOMEPAGE_URL}…"
if command -v open >/dev/null 2>&1; then
  open "${HOMEPAGE_URL}" >/dev/null 2>&1 || true
elif command -v xdg-open >/dev/null 2>&1; then
  xdg-open "${HOMEPAGE_URL}" >/dev/null 2>&1 || true
else
  echo "Please open ${HOMEPAGE_URL} manually (no opener found)." >&2
fi

echo "Stack is up. Follow logs with '${COMPOSE_CMD} logs -f'."



