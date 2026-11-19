FROM node:24-bookworm AS assets
WORKDIR /app
COPY packages/Webkul/Admin/package*.json packages/Webkul/Admin/
COPY packages/Webkul/Installer/package*.json packages/Webkul/Installer/
COPY packages/Webkul/WebForm/package*.json packages/Webkul/WebForm/
RUN cd packages/Webkul/Admin && npm ci --no-audit --no-fund \
    && cd ../Installer && npm ci --no-audit --no-fund \
    && cd ../WebForm && npm ci --no-audit --no-fund
COPY public public
COPY packages/Webkul/Admin packages/Webkul/Admin
COPY packages/Webkul/Experiment/src/Resources packages/Webkul/Experiment/src/Resources
COPY packages/Webkul/Security/src/Resources packages/Webkul/Security/src/Resources
COPY packages/Webkul/Installer packages/Webkul/Installer
COPY packages/Webkul/WebForm packages/Webkul/WebForm
RUN cd packages/Webkul/Admin && npm run build \
    && cd ../Installer && npm run build \
    && cd ../WebForm && npm run build

FROM php:8.3-apache-bookworm AS base
# The upgrade pulls Debian security fixes published after the base image was built
# (Apache in particular), so the Trivy gate in CI scans a patched image.
RUN apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS curl git unzip default-mysql-client libfreetype6-dev libicu-dev libjpeg62-turbo-dev \
    libkrb5-dev libc-client-dev libonig-dev libpng-dev libssl-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j"$(nproc)" bcmath calendar exif gd imap intl opcache pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite headers remoteip env setenvif \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'RemoteIPHeader CF-Connecting-IP'; \
        echo 'RemoteIPInternalProxy 127.0.0.1 ::1 172.31.250.0/28'; \
        echo 'RemoteIPTrustedProxy 173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22'; \
        echo 'RemoteIPTrustedProxy 141.101.64.0/18 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20'; \
        echo 'RemoteIPTrustedProxy 197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13'; \
        echo 'RemoteIPTrustedProxy 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22'; \
        echo 'RemoteIPTrustedProxy 2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32'; \
        echo 'RemoteIPTrustedProxy 2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32'; \
        echo 'Header always set X-Content-Type-Options "nosniff"'; \
        echo 'Header always set Referrer-Policy "strict-origin-when-cross-origin"'; \
        echo 'Header unset Permissions-Policy'; \
        echo 'Header always set Permissions-Policy "accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()" "expr=%{THE_REQUEST} !~ m#^(GET|HEAD|POST)\\s+/(tma|admin/(assistant|agent/briefing))(/|\\?|\\s)#"'; \
        echo 'Header always set Permissions-Policy "accelerometer=(), camera=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(self), payment=(), usb=()" "expr=%{THE_REQUEST} =~ m#^(GET|HEAD|POST)\\s+/(tma|admin/(assistant|agent/briefing))(/|\\?|\\s)#"'; \
        echo '<If "%{HTTPS} == '\''on'\'' || (%{HTTP:X-Forwarded-Proto} == '\''https'\'' && (%{CONN_REMOTE_ADDR} -ipmatch '\''127.0.0.1/32'\'' || %{CONN_REMOTE_ADDR} == '\''::1'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''172.31.250.0/28'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''173.245.48.0/20'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''103.21.244.0/22'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''103.22.200.0/22'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''103.31.4.0/22'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''141.101.64.0/18'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''108.162.192.0/18'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''190.93.240.0/20'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''188.114.96.0/20'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''197.234.240.0/22'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''198.41.128.0/17'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''162.158.0.0/15'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''104.16.0.0/13'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''104.24.0.0/14'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''172.64.0.0/13'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''131.0.72.0/22'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''2400:cb00::/32'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''2606:4700::/32'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''2803:f800::/32'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''2405:b500::/32'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''2405:8100::/32'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''2a06:98c0::/29'\'' || %{CONN_REMOTE_ADDR} -ipmatch '\''2c0f:f248::/32'\''))">'; \
        echo '    SetEnv HTTPS on'; \
        echo '    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"'; \
        echo '</If>'; \
        echo 'Header unset Content-Security-Policy'; \
        echo 'Header always set X-Frame-Options "SAMEORIGIN" "expr=%{THE_REQUEST} !~ m#^(GET|HEAD)\\s+(/web-forms/forms/[A-Za-z0-9_-]+/form\\.html|/tma(/[A-Za-z0-9_/-]*)?)(\\?[^\\s]*)?\\s+HTTP/#"'; \
        echo 'Header always set Content-Security-Policy "frame-ancestors '\''self'\''" "expr=%{THE_REQUEST} !~ m#^(GET|HEAD)\\s+(/web-forms/forms/[A-Za-z0-9_-]+/form\\.html|/tma(/[A-Za-z0-9_/-]*)?)(\\?[^\\s]*)?\\s+HTTP/#"'; \
        echo 'Header always set Content-Security-Policy "frame-ancestors '\''self'\'' https://web.telegram.org https://*.telegram.org" "expr=%{THE_REQUEST} =~ m#^(GET|HEAD)\\s+/tma(/[A-Za-z0-9_/-]*)?(\\?[^\\s]*)?\\s+HTTP/#"'; \
        echo '<LocationMatch "^/(admin|installer|webform)/build/assets/">'; \
        echo '    Header set Cache-Control "public, max-age=31536000, immutable"'; \
        echo '</LocationMatch>'; \
        echo '<DirectoryMatch "/\\.(?!well-known)">'; \
        echo '    Require all denied'; \
        echo '</DirectoryMatch>'; \
    } > /etc/apache2/conf-enabled/z-luminara-hardening.conf \
    && apache2ctl -t \
    && { \
        echo 'expose_php = Off'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 192'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 1'; \
    } > "$PHP_INI_DIR/conf.d/z-luminara.ini" \
    && rm -rf /tmp/pear /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public ./public
COPY docker/entrypoint.sh /usr/local/bin/luminara-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/luminara-entrypoint \
    && chmod +x /usr/local/bin/luminara-entrypoint \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
ENV APACHE_RUN_USER=www-data APACHE_RUN_GROUP=www-data
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1

FROM base AS dev
RUN composer install --no-interaction --no-scripts --prefer-dist \
    && rm -rf bootstrap/cache/*.php storage/installed storage/installing \
    && chown -R www-data:www-data storage bootstrap/cache
ENTRYPOINT ["luminara-entrypoint"]
CMD ["apache2-foreground"]

FROM base AS production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'opcache.validate_timestamps = 0\n' >> "$PHP_INI_DIR/conf.d/z-luminara.ini" \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist \
    && rm -rf bootstrap/cache/*.php storage/installed storage/installing \
    && chown -R www-data:www-data storage bootstrap/cache
ENTRYPOINT ["luminara-entrypoint"]
CMD ["apache2-foreground"]
