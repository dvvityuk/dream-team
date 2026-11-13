#!/bin/bash
set -euo pipefail

APP=/var/www/dream-team
cd "$APP"

uid="$(stat -c '%u' "$APP")"
gid="$(stat -c '%g' "$APP")"
if [[ "$uid" -ge 100 && "$gid" -ge 100 ]]; then
  if [[ "$(id -u www-data)" != "$uid" || "$(id -g www-data)" != "$gid" ]]; then
    groupmod -o -g "$gid" www-data
    usermod -o -u "$uid" -g www-data www-data
  fi
else
  uid=33
  gid=33
fi

mkdir -p /run/php /var/lib/nginx /var/log/nginx /var/cache/nginx \
  "$APP/data/sessions" \
  "$APP/data/logs" \
  "$APP/data/albums" \
  "$APP/data/mail/feedback/cur" \
  "$APP/data/mail/feedback/new" \
  "$APP/data/mail/feedback/tmp" \
  "$APP/images"
chown -R www-data:www-data /var/lib/nginx /var/log/nginx /var/cache/nginx /run/php
chown www-data:www-data \
  "$APP/data" \
  "$APP/data/sessions" \
  "$APP/data/logs" \
  "$APP/data/albums" \
  "$APP/data/mail" \
  "$APP/data/mail/feedback" \
  "$APP/data/mail/feedback/cur" \
  "$APP/data/mail/feedback/new" \
  "$APP/data/mail/feedback/tmp" \
  "$APP/images"

cp "$APP/nginx/qdigest-site.conf" /etc/nginx/snippets/qdigest-site.conf
cp "$APP/docker/nginx.conf" /etc/nginx/sites-enabled/qdigest.conf
rm -f /etc/nginx/sites-enabled/default
cp "$APP/docker/php-fpm.conf" /usr/local/etc/php-fpm.d/dream-team.conf
rm -f /usr/local/etc/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/docker.conf

while IFS= read -r line || [[ -n "$line" ]]; do
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  postconf -e "$line"
done < "$APP/postfix/local.cf"
postconf -e "maillog_file = /var/log/mail.log"
postconf -e "virtual_mailbox_base = ${APP}/data/mail"
postconf -e "virtual_uid_maps = static:${uid}"
postconf -e "virtual_gid_maps = static:${gid}"
postconf -e "virtual_minimum_uid = ${uid}"
install -m 644 "$APP/postfix/virtual" /etc/postfix/virtual
install -m 644 "$APP/postfix/vmailbox" /etc/postfix/vmailbox
postmap /etc/postfix/virtual
postmap /etc/postfix/vmailbox
newaliases >/dev/null 2>&1 || true
postfix check

php-fpm -F &
fpm_pid=$!
for _ in $(seq 1 50); do
  if [[ -S /run/php/php-dream-team.sock ]]; then
    break
  fi
  if ! kill -0 "$fpm_pid" 2>/dev/null; then
    echo "php-fpm не запустился" >&2
    exit 1
  fi
  sleep 0.1
done
if [[ ! -S /run/php/php-dream-team.sock ]]; then
  echo "Нет сокета php-fpm" >&2
  exit 1
fi

postfix start
nginx -g 'daemon off;' &
nginx_pid=$!

stop_all() {
  local code="${1:-0}"
  nginx -s quit >/dev/null 2>&1 || true
  postfix stop >/dev/null 2>&1 || true
  kill "$fpm_pid" >/dev/null 2>&1 || true
  wait "$fpm_pid" >/dev/null 2>&1 || true
  wait "$nginx_pid" >/dev/null 2>&1 || true
  exit "$code"
}
trap 'stop_all 0' TERM INT

set +e
wait -n "$fpm_pid" "$nginx_pid"
code=$?
set -e
stop_all "$code"
