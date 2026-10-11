#!/bin/sh
# ---------------------------------------------------------------------------
# Entrypoint контейнера приложения.
# Гарантирует наличие зависимостей и прав, затем запускает CMD.
# ---------------------------------------------------------------------------
set -e

cd /var/www/html

# Composer-зависимости: ставим, если vendor отсутствует
# (актуально при bind-mount проекта без папки vendor).
if [ ! -f vendor/autoload.php ] && [ -f composer.json ]; then
    echo "[entrypoint] vendor/ отсутствует — выполняю composer install..."
    composer install --no-interaction --no-progress --prefer-dist --no-scripts || \
        echo "[entrypoint] composer install не удался, продолжаю без него"
fi

# Права на запись в uploads (на Windows bind-mount chown может не сработать — это ок)
if [ -d uploads ]; then
    chown -R www-data:www-data uploads 2>/dev/null || true
fi

exec "$@"
