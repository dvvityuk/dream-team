#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MAILBOX="nastasya_zablotskaya@mail.ru"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Запустите от root: sudo bash postfix/local-install.sh" >&2
  exit 1
fi

production_host=0
if [[ -d /etc/letsencrypt/live/qdigest.ru || -e /etc/nginx/sites-enabled/qdigest.ru ]]; then
  production_host=1
fi

if ! dpkg -s postfix >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  if [[ "$production_host" -eq 1 ]]; then
    mailname="qdigest.ru"
  else
    mailname="dream-team.local"
  fi
  debconf-set-selections <<EOF
postfix postfix/mailname string ${mailname}
postfix postfix/main_mailer_type select Local only
postfix postfix/destinations string localhost, localhost.localdomain
EOF
  apt-get update
  apt-get install -y postfix ca-certificates
fi

if [[ "$production_host" -eq 1 && ! -f /etc/postfix/sasl_passwd ]]; then
  echo "На qdigest.ru локальная копия писем не включается. Для отправки в Mail.ru выполните: sudo bash postfix/install.sh" >&2
  bash "${ROOT}/postfix/enable-with-site.sh"
  exit 0
fi

if [[ -f /etc/postfix/sasl_passwd ]]; then
  echo "Пароль Mail.ru уже сохранён. Postfix остаётся пересылкой на smtp.mail.ru."
  bash "${ROOT}/postfix/enable-with-site.sh"
  systemctl restart postfix
  exit 0
fi

while IFS= read -r line || [[ -n "$line" ]]; do
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  postconf -e "$line"
done < "${ROOT}/postfix/local.cf"

uid="$(stat -c '%u' "$ROOT")"
gid="$(stat -c '%g' "$ROOT")"
postconf -e "virtual_mailbox_base = ${ROOT}/data/mail"
postconf -e "virtual_uid_maps = static:${uid}"
postconf -e "virtual_gid_maps = static:${gid}"

install -m 644 "${ROOT}/postfix/virtual" /etc/postfix/virtual
install -m 644 "${ROOT}/postfix/vmailbox" /etc/postfix/vmailbox
postmap /etc/postfix/virtual
postmap /etc/postfix/vmailbox

mkdir -p "${ROOT}/data/mail"
chown "${uid}:${gid}" "${ROOT}/data/mail"
chmod 770 "${ROOT}/data/mail"

postfix check
bash "${ROOT}/postfix/enable-with-site.sh"
systemctl restart postfix

token="qdigest-local-$(date +%s)"
runuser -u www-data -- /usr/sbin/sendmail -t -i -f "$MAILBOX" <<EOF
Subject: Q digest local
From: ${MAILBOX}
To: ${MAILBOX}
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8

${token}
EOF

mailbox="${ROOT}/data/mail/feedback/new"
found=""
for _ in 1 2 3 4 5 6 7 8 9 10 11 12; do
  if compgen -G "${mailbox}/*" >/dev/null; then
    found="$(grep -l -F "$token" "${mailbox}"/* 2>/dev/null | head -n 1 || true)"
    if [[ -n "$found" ]]; then
      break
    fi
  fi
  sleep 0.5
done

if [[ -z "$found" ]]; then
  echo "Postfix запущен, но проверочное письмо не попало в ${mailbox}." >&2
  journalctl -u postfix --no-pager -n 40 || true
  tail -n 40 /var/log/mail.log 2>/dev/null || true
  exit 1
fi

rm -f "$found"
echo "Локальный Postfix принимает почту с сайта. Письма «Напиши мне» сохраняются в ${ROOT}/data/mail/feedback/."
