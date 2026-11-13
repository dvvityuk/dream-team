#!/bin/bash
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Запустите от root: sudo bash postfix/enable-with-site.sh" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DROPIN_SRC="${ROOT}/systemd/qdigest-postfix.conf"

if [[ ! -f "$DROPIN_SRC" ]]; then
  echo "Нет файла ${DROPIN_SRC}" >&2
  exit 1
fi

install_dropin() {
  local unit="$1"
  local dir="/etc/systemd/system/${unit}.d"
  mkdir -p "$dir"
  cp "$DROPIN_SRC" "${dir}/qdigest-postfix.conf"
}

php_units=()
shopt -s nullglob
for pool in /etc/php/*/fpm/pool.d/dream-team.conf; do
  ver="$(awk -F/ '{print $4}' <<<"$pool")"
  unit="php${ver}-fpm.service"
  if [[ -f "/lib/systemd/system/${unit}" || -f "/usr/lib/systemd/system/${unit}" ]]; then
    php_units+=("$unit")
    if ! grep -q 'sendmail_path' "$pool"; then
      printf '\nphp_admin_value[sendmail_path] = "/usr/sbin/sendmail -t -i"\n' >> "$pool"
      systemctl restart "php${ver}-fpm"
    fi
  fi
done
shopt -u nullglob

if [[ ${#php_units[@]} -eq 0 ]]; then
  php_units+=(php8.3-fpm.service)
fi

install_dropin nginx.service
for unit in "${php_units[@]}"; do
  if [[ -f "/lib/systemd/system/${unit}" || -f "/usr/lib/systemd/system/${unit}" ]]; then
    install_dropin "$unit"
  fi
done

systemctl daemon-reload
systemctl enable postfix
systemctl enable nginx
for unit in "${php_units[@]}"; do
  if [[ -f "/lib/systemd/system/${unit}" || -f "/usr/lib/systemd/system/${unit}" ]]; then
    systemctl enable "$unit"
    systemctl start "$unit"
  fi
done
systemctl start postfix
systemctl start nginx
