#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  echo "Нужен Docker и Docker Compose." >&2
  exit 1
fi

export QDIGEST_PORT="${QDIGEST_PORT:-80}"

if command -v crontab >/dev/null 2>&1; then
  cron_file="$(mktemp)"
  crontab -l 2>/dev/null | grep -v 'daily-github-push.sh' >"$cron_file" || true
  echo "0 6 * * * ${ROOT}/scripts/daily-github-push.sh" >>"$cron_file"
  crontab "$cron_file"
  rm -f "$cron_file"
else
  echo "cron не найден. Ежедневная отправка на GitHub не включена." >&2
fi

docker compose up -d --build
echo "Сайт запущен в контейнере: http://127.0.0.1:${QDIGEST_PORT}/"
echo "Письма «Напиши мне» сохраняются в ${ROOT}/data/mail/feedback/."
