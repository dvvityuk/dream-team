FROM php:8.3-fpm-bookworm

RUN printf '#!/bin/sh\nexit 101\n' > /usr/sbin/policy-rc.d \
    && chmod +x /usr/sbin/policy-rc.d \
    && apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        nginx \
        postfix \
        libsasl2-modules \
        ca-certificates \
        cron \
        git \
        tzdata \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd mbstring \
    && rm -f /usr/sbin/policy-rc.d \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/docker.conf \
    && sed -i 's|;error_log = log/php-fpm.log|error_log = /proc/self/fd/2|' /usr/local/etc/php-fpm.conf \
    && sed -i 's|;pid = run/php-fpm.pid|pid = /run/php/php-fpm.pid|' /usr/local/etc/php-fpm.conf \
    && sed -i 's|error_log /var/log/nginx/error.log;|error_log /dev/stderr warn;|' /etc/nginx/nginx.conf \
    && sed -i 's|access_log /var/log/nginx/access.log;|access_log /dev/stdout;|' /etc/nginx/nginx.conf \
    && mkdir -p /run/php /var/www/dream-team

COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/dream-team.conf
COPY docker/nginx.conf /etc/nginx/sites-enabled/qdigest.conf
COPY docker/entrypoint.sh /usr/local/bin/qdigest-entrypoint
COPY nginx/qdigest-site.conf /etc/nginx/snippets/qdigest-site.conf
COPY . /var/www/dream-team

RUN chmod 755 /usr/local/bin/qdigest-entrypoint \
    && mkdir -p /var/www/dream-team/data/sessions /var/www/dream-team/data/logs /var/www/dream-team/data/mail/feedback/cur /var/www/dream-team/data/mail/feedback/new /var/www/dream-team/data/mail/feedback/tmp /var/www/dream-team/images

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/qdigest-entrypoint"]
