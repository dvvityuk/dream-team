#!/bin/bash
set -euo pipefail

MAILBOX="nastasya_zablotskaya@mail.ru"
RELAYHOST="[smtp.mail.ru]:465"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Запустите от root: sudo bash postfix/install.sh" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
debconf-set-selections <<EOF
postfix postfix/mailname string qdigest.ru
postfix postfix/main_mailer_type select Satellite system
postfix postfix/relayhost string ${RELAYHOST}
EOF

apt-get update
apt-get install -y postfix libsasl2-modules ca-certificates

while IFS= read -r line || [[ -n "$line" ]]; do
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
  postconf -e "$line"
done < "$ROOT/postfix/qdigest.cf"

if [[ -f /etc/postfix/sasl_passwd ]]; then
  read -r -p "/etc/postfix/sasl_passwd уже есть. Заменить пароль? [y/N] " replace
else
  replace="y"
fi

if [[ "$replace" == "y" || "$replace" == "Y" ]]; then
  read -r -s -p "Пароль внешнего приложения Mail.ru для ${MAILBOX}: " app_password
  echo
  if [[ -z "$app_password" ]]; then
    echo "Пароль пустой, установка остановлена." >&2
    exit 1
  fi
  umask 077
  printf '%s %s:%s\n' "$RELAYHOST" "$MAILBOX" "$app_password" > /etc/postfix/sasl_passwd
  unset app_password
fi
chmod 600 /etc/postfix/sasl_passwd
postmap /etc/postfix/sasl_passwd
chmod 600 /etc/postfix/sasl_passwd.db

umask 077
cat > /etc/postfix/generic <<EOF
www-data@qdigest.ru ${MAILBOX}
www-data@mail.qdigest.ru ${MAILBOX}
root@qdigest.ru ${MAILBOX}
root@mail.qdigest.ru ${MAILBOX}
EOF
postmap /etc/postfix/generic
chmod 600 /etc/postfix/generic /etc/postfix/generic.db

systemctl restart postfix
bash "${ROOT}/postfix/enable-with-site.sh"

printf 'Subject: Q digest\nFrom: %s\nTo: %s\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\nПроверка почтового агента qdigest.ru.\n' \
  "$MAILBOX" "$MAILBOX" | /usr/sbin/sendmail -t -i -f "$MAILBOX"

sleep 3
echo "--- mail.log ---"
tail -n 40 /var/log/mail.log || true
