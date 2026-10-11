# iCook — «Я готовлю, ты ешь!»

Сайт доставки домашней еды: витрина блюд, корзина, личный кабинет покупателя
и кабинет продавца (шефа), а также JSON-API на PHP.

Фронтенд — статические HTML/CSS/JS страницы, бэкенд — PHP 8.2 + MySQL 8.
Всё окружение поднимается одной командой через Docker Compose.

---

## Быстрый старт

```bash
# 1. Создать файл окружения (в нём заданы порты и пароли для локального запуска)
cp .env.example .env        # Windows CMD: copy .env.example .env

# 2. Собрать и поднять контейнеры
docker compose up -d --build

# 3. Открыть сайт
#    http://localhost:8080
```

Полезные команды:

```bash
docker compose ps            # статус контейнеров
docker compose logs -f web   # логи приложения
docker compose down          # остановить
docker compose down -v       # остановить и УДАЛИТЬ данные БД
```

## Сервисы и порты

| Сервис      | Адрес                     | Назначение                        |
|-------------|---------------------------|-----------------------------------|
| web         | http://localhost:8080     | само приложение (Apache + PHP)    |
| phpMyAdmin  | http://localhost:8081     | веб-интерфейс к базе данных       |
| Mailpit     | http://localhost:8025     | все письма от `mail()` попадают сюда |
| MySQL       | localhost:3307            | прямой доступ к БД (клиентом)     |

Логины для phpMyAdmin — значения `DB_USER` / `DB_PASSWORD` из `.env`
(по умолчанию `icook` / `icook_secret`).

## Структура проекта

```
Icook/
├─ *.html                 # страницы фронтенда (index, menu, cart, profile, seller, ...)
├─ api/                   # PHP-эндпоинты, отдают JSON
│   ├─ db.php             # подключение к MySQL (параметры из переменных окружения)
│   ├─ login.php          # вход
│   ├─ register.php       # регистрация
│   ├─ send_verify.php    # письмо подтверждения email
│   ├─ verify_token.php   # проверка токена
│   ├─ reset_password.php # запрос сброса пароля
│   ├─ set_new_password.php
│   ├─ change_password.php
│   ├─ profile.php        # данные текущего пользователя
│   └─ delete_account.php
├─ assets/                # css, js, изображения, apk-приложение
├─ mail/                  # legacy-копия Roundcube (webmail), в рантайме не участвует
├─ docker/                # конфиги Docker (php.ini, msmtp, SQL-инициализация)
├─ uploads/               # загруженные файлы (том Docker)
├─ Dockerfile
└─ docker-compose.yml
```

## Конфигурация

Все секреты и порты — в `.env` (не коммитится). Шаблон — `.env.example`.
Приложение читает настройки БД из переменных окружения `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASSWORD`.

## База данных

Схема лежит в `docker/mysql/init/01_schema.sql` и применяется автоматически
при первом запуске (пустой том `db_data`). Чтобы применить изменения к уже
существующей БД, пересоздайте том: `docker compose down -v && docker compose up -d`.

## Лицензия

Учебный/личный проект. Все права на код принадлежат владельцу репозитория.
