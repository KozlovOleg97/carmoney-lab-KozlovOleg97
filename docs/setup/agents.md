# Агенты

## Planner

Planner — основной агент для подготовки плана изменений до написания кода.

Он может читать код, `AGENTS.md` и `docs/setup/code_map.md`, но не может изменять код проекта или выполнять bash-команды.

Запись разрешена только в `docs/plan/**`.

## Scout

Scout — субагент-разведчик для поиска конкретных мест в кодовой базе.

Он не может изменять файлы и выполнять bash-команды. В ответе возвращает файл, строку и краткое описание найденного места.

## Результат Scout по mileage

Scout нашёл следующие места:

- `backend/src/Repository/ApplicationRepository.php:19` — PHPDoc параметра `$input`, содержащего поле `mileage`.
- `backend/src/Repository/ApplicationRepository.php:38-39` — `INSERT` в `vehicles(mileage_km)` с параметром `:mileage`.
- `backend/src/Repository/ApplicationRepository.php:45` — привязка `:mileage` к `$input['mileage']`.
- `backend/src/Repository/ApplicationRepository.php:68` — чтение `v.mileage_km` при выборке заявки.
- `db/schema.sql:22` — поле `vehicles.mileage_km`.
- `db/seed.sql:31` — заполнение `mileage_km` тестовыми данными.
- `tests/Unit/ApplicationValidatorTest.php:34` — тест с `mileage = 84000`.
- `tests/Unit/AssessmentServiceTest.php:38` — тест с `mileage = 96000`.

В `DecisionEngine` и `AssessmentService` пробег сейчас непосредственно не используется при принятии решения.