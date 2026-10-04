# План MILEAGE: правило пробега в решении по заявке

Задача: `mileage <= 400 000` — решение считается по текущей логике LTV;
`mileage > 400 000` — заявка, которая по LTV получила бы `approve`,
понижается до `review`; `review` и `reject` по LTV пробег не меняет
(`docs/intent/intent_MILEAGE.md`, `docs/spec/spec_MILEAGE.md`,
REQ-MILEAGE-02, REQ-MILEAGE-03).

Опора: `docs/setup/code_map.md` (точка вставки — `DecisionEngine::decide()`,
проверка пробега в `DecisionEngine::decide()` как пост-обработка LTV-решения:
понижает только approve до review), сводка Scout по mileage из
`docs/setup/agents.md` (репозиторий/схема/сид/тесты — пробег там не участвует
в решении, менять их не нужно).

Выбранная схема: порог лежит в `backend/config/rules.php` (конвенция проекта —
все пороги в конфиге, код значений не хранит), пробег передаётся вторым
аргументом в `DecisionEngine::decide(float $ltv, int $mileage)` и проверяется
первым. Альтернатива «проверять в `AssessmentService` до вызова `decide()`»
отвергнута: она допускается code_map, но размазывает логику решения по двум
классам, а `DecisionEngine` перестаёт быть единственным источником решения.

Диапазон валидации не меняется: `0 <= mileage <= 500 000`
(`vehicle.max_mileage_km`). Интервал 400 001..500 000 остаётся валидным входом; при LTV-approve он даёт
`review`, при LTV-review — `review`, при LTV-reject — `reject` (смягчения
reject нет); всё, что больше 500 000, как раньше —
`ValidationException` до расчёта решения.

## Файлы

1. `backend/config/rules.php` — в секцию `vehicle` добавить ключ `'review_mileage_km' => 400000` с комментарием; существующие пороги не трогать.
2. `backend/src/Domain/DecisionEngine.php` — новый параметр конструктора `int $reviewMileageKm`, сигнатура `decide(float $ltv, int $mileage)`; решение сначала считается по существующим веткам LTV без изменений, затем, если оно `APPROVE` и `$mileage > $reviewMileageKm`, понижается до `REVIEW`; обновить PHPDoc класса и конструктора.
3. `backend/src/Domain/AssessmentService.php` — строка 33: вызвать `$this->decisionEngine->decide($ltv, $input['mileage'])`.
4. `backend/src/AppFactory.php` — строка 37: `new DecisionEngine($rules['ltv'], (int) $rules['vehicle']['review_mileage_km'])`.
5. `tests/Unit/DecisionEngineTest.php` — `setUp()`: второй аргумент конструктора `400000`; существующий провайдер LTV вызывает `decide($ltv, 100000)`; новый провайдер граничных значений пробега.
6. `tests/Unit/AssessmentServiceTest.php` — хелпер `payload()` получает параметр `int $mileage = 96000` (существующие тесты не меняют ожиданий); новые тесты границ пробега и пустого пробега.

Не меняем: `ApplicationValidator.php` (диапазон 0..500 000 остаётся),
`ApplicationRepository.php`, `db/schema.sql`, `db/seed.sql`,
`backend/src/Http/ApplicationController.php` (решение получает уже готовое).

## Шаги

1. `rules.php`: добавить `vehicle.review_mileage_km = 400000` (значение из задачи; новый ключ, не изменение существующих).
2. `DecisionEngine`: параметр конструктора `int $reviewMileageKm` (со свойством), параметр `int $mileage` в `decide()`; решение по существующим веткам LTV считается без изменений, после чего правило пробега применяется только к `APPROVE`: `if ($decision === self::APPROVE && $mileage > $this->reviewMileageKm) { $decision = self::REVIEW; }` — review и reject по LTV пробег не меняет (REQ-MILEAGE-02, REQ-MILEAGE-03); обновить PHPDoc (включить правило пробега в описание решения).
3. `AssessmentService`: передать `$input['mileage']` вторым аргументом в `decide()` (валидатор гарантирует там int).
4. `AppFactory`: прокинуть `(int) $rules['vehicle']['review_mileage_km']` в конструктор `DecisionEngine`.
5. `DecisionEngineTest`: обновить конструктор и все вызовы `decide()`; добавить провайдер границ пробега (см. Тесты).
6. `AssessmentServiceTest`: параметризовать `payload()` по пробегу; добавить тесты границ и пустого пробега (см. Тесты).
7. Проверка: `make test` (PHPUnit) и `make lint` (`php -l`); убедиться, что существующие тесты (mileage 84 000 и 96 000) зелёные без правки их ожиданий.

## Тесты

Граничные значения — отдельными строками. Порог 400 000: «не больше» — граница
включена, за ней сразу `review`.

### DecisionEngineTest (юнит, напрямую)

`setUp()`: `new DecisionEngine(['approve_max' => 60.0, 'review_max' => 85.0], 400000)`.
Новый провайдер с фиксированным LTV 50.0 (зона approve, чтобы видеть эффект только пробега):

- 399999 → `APPROVE` — пробег под порогом, решение по LTV;
- 400000 → `APPROVE` — граница «не больше 400 000» включена;
- 400001 → `REVIEW` — первое значение за границей, approve по LTV понижен до review (REQ-MILEAGE-02, AC-MILEAGE-04);
- 500000 → `REVIEW` — верхняя граница валидации `max_mileage_km`, всё ещё `review` (AC-MILEAGE-05).

Отдельные случаи взаимодействия (REQ-MILEAGE-03, AC-MILEAGE-06, AC-MILEAGE-07):
- `decide(70.0, 400001)` → `REVIEW` — review-зона LTV остаётся review;
- `decide(95.0, 400001)` → `REJECT` — reject по LTV не смягчается пробегом.

Существующий провайдер `ltvValues`: вызов меняется на `decide($ltv, 100000)`,
все шесть ожиданий сохраняются.

### AssessmentServiceTest (юнит, через `assess()`)

LTV 50% (`payload(450000, 900000)`), варьируется пробег:

- mileage 399999 → `decision = approve`, `approved_limit = 450000`;
- mileage 400000 → `decision = approve`, `approved_limit = 450000`;
- mileage 400001 → `decision = review`, `approved_limit = 0` (AC-MILEAGE-04, AC-MILEAGE-09);
- mileage 400001 при LTV > 85% (`payload(900000, 1000000)`) → `decision = reject` — reject не смягчается (AC-MILEAGE-07);
- mileage 400001 при 60% <= LTV <= 85% → `decision = review` — review-зона не меняется (AC-MILEAGE-06);
- пустой пробег (ключ `mileage` отсутствует) → `ValidationException`, `errors()` содержит ключ `mileage` (AC-MILEAGE-12);
- пустой пробег (`'mileage' => null`) → `ValidationException`, `errors()` содержит ключ `mileage`.

Тест пустого пробега — в стиле `testRejectsAmountBelowMinimum` из
`ApplicationValidatorTest` (try/catch + `assertArrayHasKey('mileage', $exception->errors())`).

Существующие тесты `AssessmentServiceTest` (96000) и `ApplicationValidatorTest`
(84000) не меняются: их пробег ниже порога, ожидания прежние.

Пустую строку `''` как пробег намеренно не тестируем: сейчас `(int) '' = 0`
и она проходит валидацию как 0 км — поведение существующее и выходит за рамки
задачи (вопрос 2 заказчику).

## Риски

1. Смена сигнатуры `decide()` ломает все точки вызова: `AssessmentService.php:33`, `DecisionEngineTest.php:23` и три места сборки `DecisionEngine` (`AppFactory.php:37`, `DecisionEngineTest.php:17`, `AssessmentServiceTest.php:27`). Пропущенное место — фатальная ошибка в рантайме. Смягчение: после правок — `make test`, `make lint` и поиск `->decide(` по репозиторию.
2. Взаимодействие с `max_mileage_km = 500000`: нельзя «упростить» задачу, снизив `max_mileage_km` до 400000 — тогда заявки с пробегом 400 001..500 000 начнут получать `ValidationException` вместо `review`, изменится контракт API и бизнес-решение. Существующий порог не трогать (правило AGENTS.md про пороги).
3. Смягчение reject: проверка пробега должна применяться только к approve, иначе заявка с пробегом 400 001+ и LTV > 85% получит `review` вместо `reject` — прямое нарушение REQ-MILEAGE-03. Реализация «сначала LTV, потом понижение approve» это исключает; тест `decide(95.0, 400001) → REJECT` фиксирует (ответ заказчика, вопрос 1 интервью, закрыт).
4. Добавление ключа в `rules.php` влияет на бизнес-решение: кладём только новый `review_mileage_km = 400000` из задачи; существующие пороги (`approve_max`, `review_max`, `max_mileage_km` и др.) и ожидания существующих тестов не подгонять.
5. Опечатка/пропуск ключа `review_mileage_km`: в `strict_types` конструктор `DecisionEngine` упадёт с TypeError (null не приводится к int). Ключ и проводку в `AppFactory` делать в одном коммите; в `AppFactory` — явный `(int)`-каст.
6. Пустая строка пробега `''` приводится к 0 и проходит как «нулевый пробег», обойдя и новое правило — существующая дыра валидации, текущей задачей не закрывается (вопрос 2).
7. Исторические данные: решение пишется в таблицу `decisions` при создании заявки, GET-эндпоинты читают сохранённое. Уже сохранённые заявки не пересчитаются и продолжат показывать прежние решения (вопрос 4).
8. Фронтенд и `mocks/` не проверялись (Scout находок о влиянии пробега на решение там не дал); радиус эффекта — только домен и его сборка.
9. Не входит в задачу: диапазон валидации пробега, репозиторий и схема БД, HTTP-контроллер, пересчёт старых заявок, задача LOAN-12 (лимит по `ltv_by_age`).

## Вопросы заказчику

Открытых вопросов нет — все закрыты заказчиком в интервью (`grill_MILEAGE.md`,
intent, раздел «Open questions»), сверено со спекой:

1. Пробег > 400 000 при плохом LTV — закрыт (вопрос 1): reject, понижается только approve.
2. «Пустой пробег» — закрыт (вопрос 2): валидация не меняется, дыра `'' → 0` вне рамок.
3. `approved_limit` при review из-за пробега — закрыт (вопрос 3): 0, как у всех не-approve решений.
4. Пересчёт сохранённых решений — закрыт (вопрос 4): нет, правило только на новые заявки.
5. Радиус задачи — закрыт (вопрос 5): только расчёт решения; порог 400 000 остаётся конфигом (`vehicle.review_mileage_km`).
