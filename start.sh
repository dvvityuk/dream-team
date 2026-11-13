#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  echo "Нужен Docker и Docker Compose." >&2
  exit 1
fi

export QDIGEST_PORT="${QDIGEST_PORT:-80}"
if [[ -z "${TZ:-}" ]]; then
  if [[ -r /etc/timezone ]]; then
    TZ="$(tr -d ' \r\n' < /etc/timezone)"
  else
    TZ="$(timedatectl show -p Timezone --value 2>/dev/null || true)"
  fi
  TZ="${TZ:-Europe/Berlin}"
  export TZ
fi

if command -v crontab >/dev/null 2>&1; then
  cron_file="$(mktemp)"
  crontab -l 2>/dev/null | grep -v 'daily-github-push.sh' >"$cron_file" || true
  crontab "$cron_file"
  rm -f "$cron_file"
fi

relay="$ROOT/data/mail-relay.env"
if [[ ! -s "$relay" && -t 0 ]]; then
  read -r -s -p "Пароль внешнего приложения Mail.ru для nastasya_zablotskaya@mail.ru: " mail_pass
  echo
  if [[ -n "${mail_pass:-}" ]]; then
    umask 077
    printf 'MAILRU_PASSWORD=%s\n' "$mail_pass" >"$relay"
  fi
  unset mail_pass
fi

docker compose up -d --build
echo "Сайт запущен в контейнере: http://127.0.0.1:${QDIGEST_PORT}/"
if [[ -s "$relay" ]]; then
  echo "Почта отправляется через Mail.ru из контейнера."
else
  echo "Нет data/mail-relay.env. Письма «Напиши мне» сохраняются в ${ROOT}/data/mail/feedback/."
fi
echo "Копия проекта отправляется на GitHub каждый день в 06:00 из контейнера."
