1) Учебный сервис предварительной оценки заявки на заём под ПТС: принимает заявку (VIN, год, пробег, оценочная стоимость, сумма, срок), считает LTV и возвращает решение `approve` / `review` / `reject`. Данные синтетические.
2) Makefile: `up` (`docker compose up -d --build`, сервис на :8080), `down`, `ps`, `logs`, `install` (`composer install`), `test` (PHPUnit локально или в контейнере), `lint` (`php -l` по `backend/` и `tests/`), `seed` (заливает `db/seed.sql` в уже поднятую БД), `help`. docker-compose.yml описывает сервисы `backend` (PHP 8 из `backend/Dockerfile`, порт `${APP_PORT:-8080}:8080`) и `db` (MySQL 8.0, порт `${DB_PORT:-3307}:3306`, initdb: `db/schema.sql` и `db/seed.sql`, healthcheck через `mysqladmin ping`).
3) Решение `approve` / `review` / `reject` считается в папке `backend/src/Domain/` (классы `DecisionEngine.php`, `LtvCalculator.php`, `AssessmentService.php`); пороги и лимиты — в `backend/config/rules.php`.

модель: MiniMax-M3 (training-2026-09-minimax-m3)
