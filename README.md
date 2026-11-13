# Q digest на VDS

Сайт: [https://qdigest.ru](https://qdigest.ru). Сервер: `135.136.177.110`. Каталог сайта на сервере: `/var/www/dream-team`.

Ниже порядок действий после того, как файлы проекта уже лежат на VDS. Команды выполняются по SSH от пользователя с `sudo`. Система — Ubuntu 24.04 или другой Debian-дистрибутив с PHP 8.3.

## Локальный запуск

Сайт, PHP и локальный почтовый агент поднимаются в одном контейнере:

```bash
bash start.sh
```

Нужен Docker с Compose. Скрипт собирает образ и запускает контейнер. Сайт открывается на `http://127.0.0.1/`. Другой порт задаётся так: `QDIGEST_PORT=8091 bash start.sh`. Каталог проекта подключён к контейнеру, поэтому правки и данные сотрудников остаются на этой машине.

Postfix внутри контейнера никуда наружу не слушает. Пока не выполнен `postfix/install.sh` с паролем Mail.ru, письма формы «Напиши мне» сохраняются в `data/mail/feedback/`. Остановить контейнер: `docker compose down`.

Каждый день в 06:00 по местному времени `scripts/daily-github-push.sh` отправляет проект на GitHub, если контейнер `qdigest` запущен. `bash start.sh` ставит эту задачу в cron. Логин и пароль лежат в `data/github-push.env` и в репозиторий не попадают. Сессии, почта и журналы тоже не отправляются.

Nginx отдаёт сайт только по имени `qdigest.ru`. Адрес `www.qdigest.ru` перенаправляется туда же. Служебные каталоги `data/`, `includes/` и `nginx/` снаружи не открываются. Этот файл тоже не скачивается: nginx закрывает адреса, которые заканчиваются на `.md`.

## 1. Зайти на сервер

```bash
ssh root@135.136.177.110
```

Если вход под другим пользователем, дальше везде оставляйте `sudo`.

Проверьте, что домен смотрит на этот сервер. Обе записи должны быть такими:

| Имя | Тип | Значение |
| --- | --- | --- |
| `qdigest.ru` | A | `135.136.177.110` |
| `www.qdigest.ru` | A | `135.136.177.110` |

На сервере:

```bash
getent hosts qdigest.ru
getent hosts www.qdigest.ru
```

В ответе должен быть `135.136.177.110`. Пока `www` не резолвится, сертификат для обоих имён не выпустится.

В панели VDS откройте входящие порты **22**, **80** и **443**. Порт 25 наружу открывать не нужно: почта уходит с сервера на `smtp.mail.ru:465`.

## 2. Положить файлы в каталог сайта

Конфиги nginx и PHP уже указывают на `/var/www/dream-team`. Если проект загружен в другое место, скопируйте его. Пример для загрузки в `/root/dream-team`:

```bash
sudo mkdir -p /var/www/dream-team
sudo rsync -a /root/dream-team/ /var/www/dream-team/
```

Дальше все команды предполагают, что вы находитесь в каталоге сайта:

```bash
cd /var/www/dream-team
```

Владелец файлов — пользователь веб-сервера. Иначе сайт не сможет сохранять сотрудников, альбомы и пароль.

```bash
sudo chown -R www-data:www-data /var/www/dream-team
sudo find /var/www/dream-team -type d -exec chmod 755 {} \;
sudo find /var/www/dream-team -type f -exec chmod 644 {} \;
```

Писать должны каталоги `data/`, `data/albums/` и `images/`. Отдельные права им не нужны: их владелец `www-data`.

## 3. Поставить пакеты

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-cli certbot
```

Проверка:

```bash
php -v
systemctl status nginx --no-pager
systemctl status php8.3-fpm --no-pager
```

Если пакета `php8.3-fpm` нет, смотрите раздел «Другая версия PHP» в конце.

Включите автозапуск:

```bash
sudo systemctl enable nginx php8.3-fpm
```

Postfix в эту команду не входит: пакет ставится на шаге 8. После него агент включён в автозапуск и поднимается вместе с nginx и php-fpm.

## 4. Подключить пул PHP

Сайт ходит в отдельный сокет `/run/php/php-dream-team.sock`, а не в общий пул `www`.

```bash
sudo cp nginx/dream-team.pool.conf /etc/php/8.3/fpm/pool.d/dream-team.conf
sudo systemctl restart php8.3-fpm
```

Пул ограничивает загрузку: один файл до 8 МБ, весь запрос до 32 МБ, не больше 20 файлов за раз. Ошибки PHP пишутся в `data/php-error.log`. Раз в сутки журнал перекладывается в `data/logs/`, файлы старше 30 суток удаляются.

## 5. Включить сайт по HTTP

Сертификата ещё нет, поэтому сначала ставится временный конфиг `nginx/dream-team.http.conf`. Он отвечает на порту 80 и отдаёт файлы проверки Let's Encrypt. Боевой `nginx/dream-team.conf` на этом шаге подключать нельзя: nginx не запустится без файлов сертификата.

```bash
sudo cp nginx/qdigest-site.conf /etc/nginx/snippets/qdigest-site.conf
sudo cp nginx/qdigest-ssl.conf /etc/nginx/snippets/qdigest-ssl.conf
sudo cp nginx/dream-team.http.conf /etc/nginx/sites-available/qdigest.ru
sudo ln -sfn /etc/nginx/sites-available/qdigest.ru /etc/nginx/sites-enabled/qdigest.ru
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

Откройте в браузере `http://qdigest.ru`. Должна появиться страница входа. Замок браузера пока будет сообщать, что соединения без шифрования. Это ожидаемо до следующего шага.

Если страница не открывается, смотрите `sudo tail -n 50 /var/log/nginx/error.log`.

## 6. Выпустить сертификат

```bash
sudo certbot certonly --webroot -w /var/www/dream-team \
  -d qdigest.ru -d www.qdigest.ru \
  --deploy-hook "systemctl reload nginx"
```

Certbot спросит адрес для уведомлений об истечении сертификата и согласие с условиями. Каталог сертификата после успеха:

`/etc/letsencrypt/live/qdigest.ru/`

Продление ставится вместе с пакетом certbot и само перечитывает nginx. Проверка таймера:

```bash
systemctl list-timers | grep certbot
```

## 7. Включить HTTPS

Теперь временный конфиг заменяется боевым. Порт 80 остаётся только для продления сертификата и перенаправляет людей на `https://qdigest.ru`.

```bash
sudo cp nginx/dream-team.conf /etc/nginx/sites-available/qdigest.ru
sudo nginx -t
sudo systemctl reload nginx
```

Проверьте оба адреса:

- `http://qdigest.ru` открывает `https://qdigest.ru`
- `https://www.qdigest.ru` открывает `https://qdigest.ru`
- в строке адреса есть замок

Повторная загрузка сниппетов нужна только если вы меняли файлы в `nginx/` после шага 5:

```bash
sudo cp nginx/qdigest-site.conf /etc/nginx/snippets/qdigest-site.conf
sudo cp nginx/qdigest-ssl.conf /etc/nginx/snippets/qdigest-ssl.conf
sudo nginx -t && sudo systemctl reload nginx
```

## 8. Настроить почту

Форма «Напиши мне» вызывает PHP `mail()`. Локальный Postfix принимает это письмо и отдаёт его в Mail.ru. Прямая отправка с VDS не подойдёт: в письме отправитель `nastasya_zablotskaya@mail.ru`, и Mail.ru примет его только через свой SMTP.

Сначала создайте пароль для внешнего приложения, не основной пароль ящика:

1. Войдите в Mail.ru под `nastasya_zablotskaya@mail.ru`.
2. Откройте «Настройки» → «Все настройки» → «Безопасность» → «Пароли для внешних приложений».
3. Создайте пароль, например с именем `qdigest`.

Затем на сервере, из каталога сайта:

```bash
cd /var/www/dream-team
sudo bash postfix/install.sh
```

Скрипт поставит Postfix, спросит этот пароль и отправит проверочное письмо. Пароль записывается в `/etc/postfix/sasl_passwd` с правами только для root и в проект не копируется. Затем он подключает агент к nginx и php-fpm, чтобы почта поднималась вместе с сайтом.

На локальной машине контейнер из `bash start.sh` письма наружу не отправляет: они лежат в `data/mail/feedback/`.

В конце скрипт печатает `/var/log/mail.log`. Удачная строка содержит `status=sent`. Письмо с темой `Q digest` должно прийти на `nastasya_zablotskaya@mail.ru`.

В панели администратора сайта отправитель должен остаться `nastasya_zablotskaya@mail.ru`. Через этот SMTP Mail.ru выпускает письма только от того же ящика.

Postfix слушает только `127.0.0.1`. Снаружи почтовый сервер не открыт.

## 9. Войти и сменить пароли

Откройте `https://qdigest.ru/login.php`.

В проекте уже есть `data/users.php` с двумя аккаунтами. Если пароли ещё не меняли, подойдут исходные:

| Аккаунт | Пароль | Что может |
| --- | --- | --- |
| `admin` | `ventra_admin` | редактирование и просмотр |
| `expert` | `ventra` | только просмотр |

Если эти пароли не подходят, в `data/users.php` уже записаны другие. Исходные из таблицы создаются заново только когда файла `data/users.php` нет.

После входа под `admin` смените пароль `expert` внизу страницы Q digest, в блоке «Пароль аккаунта expert».

Пароль `admin` в интерфейсе не меняется. Новый хеш можно сделать на сервере и вписать в `data/users.php` вместо старого значения `password` у `admin`:

```bash
php -r "echo password_hash('новый-пароль', PASSWORD_DEFAULT), PHP_EOL;"
```

После правки файла:

```bash
sudo chown www-data:www-data /var/www/dream-team/data/users.php
```

## 10. Проверить сайт

Под `expert`:

- открываются Q digest и Team building;
- переключатель «Тёмная / Светлая» запоминается после обновления страницы;
- кнопка редактирования не появляется.

Под `admin`:

- на Q digest сохраняются сотрудники и дата в заголовке;
- в Team building создаётся папка и загружается фото;
- форма «Напиши мне» показывает успех, а письмо приходит на Mail.ru.

Прямые адреса служебных данных должны отвечать отказом:

```bash
curl -I https://qdigest.ru/data/users.php
curl -I https://qdigest.ru/employees.json
```

Ожидается `403`.

## Обновление файлов

Новую копию проекта снова положите в `/var/www/dream-team`, не затирая боевые `data/` и `images/`, если в копии их нет или они старее. Безопасный вариант — копировать код, а каталоги с загруженными фото не трогать.

После копирования:

```bash
sudo chown -R www-data:www-data /var/www/dream-team
sudo cp nginx/dream-team.pool.conf /etc/php/8.3/fpm/pool.d/dream-team.conf
sudo cp nginx/qdigest-site.conf /etc/nginx/snippets/qdigest-site.conf
sudo cp nginx/qdigest-ssl.conf /etc/nginx/snippets/qdigest-ssl.conf
sudo cp nginx/dream-team.conf /etc/nginx/sites-available/qdigest.ru
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
sudo bash postfix/enable-with-site.sh
```

Почту заново ставить не нужно, пока не меняется ящик или пароль приложения. Повторный запуск `sudo bash postfix/install.sh` спрашивает, заменять ли уже сохранённый пароль.

## Если что-то не работает

**`nginx -t` ругается на сертификат.** Боевой конфиг включили раньше certbot. Верните временный файл, выпустите сертификат и только потом копируйте `nginx/dream-team.conf`:

```bash
sudo cp nginx/dream-team.http.conf /etc/nginx/sites-available/qdigest.ru
sudo nginx -t && sudo systemctl reload nginx
```

**Certbot не видит домен.** С сервера `getent hosts qdigest.ru` и `getent hosts www.qdigest.ru` должны показывать `135.136.177.110`. Порт 80 должен быть открыт, а в сайтах nginx не должно остаться `sites-enabled/default`.

**Страница 502.** Не запущен пул или не совпадает сокет:

```bash
systemctl status php8.3-fpm --no-pager
ls -l /run/php/php-dream-team.sock
```

**Вход пишет «Нет прав на запись в каталог data».** Владелец каталога не `www-data`. Повторите `chown` из шага 2.

**После верного пароля снова открывается форма входа.** Сессия пишется в `/var/lib/php/sessions`. Если `df -h` или `df -i` показывают полный раздел, PHP не может сохранить вход: удалите старые `sess_*` в этом каталоге и освободите место.

**Письмо не уходит.** Смотрите `sudo tail -n 80 /var/log/mail.log`. `SASL authentication failed` значит, что в Postfix попал обычный пароль ящика или старый пароль приложения. Создайте пароль заново и запустите `sudo bash postfix/install.sh`, ответив `y` на замену. `status=deferred` с таймаутом значит, что провайдер VDS режет исходящий порт 465: это открывается в поддержке хостинга.

В панели сайта отправитель при этом должен оставаться `nastasya_zablotskaya@mail.ru`.

## Другая версия PHP

Код рассчитан на PHP 8. Сокет в nginx называется `php-dream-team.sock` и берётся из файла пула, поэтому при PHP 8.2 меняется только путь, куда кладётся пул, и имя службы:

```bash
sudo apt install -y php8.2-fpm php8.2-cli
sudo cp nginx/dream-team.pool.conf /etc/php/8.2/fpm/pool.d/dream-team.conf
sudo systemctl restart php8.2-fpm
```

В командах выше заменяйте `php8.3-fpm` на установленную версию.
