# syntax=docker/dockerfile:1
# ---------------------------------------------------------------------------
# iCook — образ приложения (PHP 8.2 + Apache)
# Годится и для локальной разработки, и как база для продакшена.
# ---------------------------------------------------------------------------
FROM php:8.2-apache

# --- Системные библиотеки, нужные расширениям PHP --------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        unzip \
        msmtp \
    && rm -rf /var/lib/apt/lists/*

# --- Расширения PHP ---------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd zip

# --- Apache: mod_rewrite, .htaccess, отключаем лишние предупреждения -------
RUN a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# --- Конфигурация PHP -------------------------------------------------------
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-icook.ini

# --- mail() -> Mailpit (в dev-окружении письма не уходят наружу) -----------
COPY docker/php/msmtprc /etc/msmtprc
RUN chmod 644 /etc/msmtprc \
    && printf 'sendmail_path = "/usr/bin/msmtp -t -i"\n' > /usr/local/etc/php/conf.d/zz-mail.ini

# --- Composer ---------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Код приложения ---------------------------------------------------------
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

# Ставим PHP-зависимости по composer.lock (если vendor уже скопирован — просто проверка)
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts \
    || echo "[build] composer install пропущен (нет сети или vendor уже на месте)"

# --- Каталог загрузок (фото блюд и т.п.) -----------------------------------
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
