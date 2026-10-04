# DZ1 Judge: повторный независимый review MILEAGE

Дата проверки: 2026-10-05. Проверены `docs/intent/intent_MILEAGE.md`,
`docs/spec/spec_MILEAGE.md`, `docs/plan/plan_MILEAGE.md`, весь production-код
в `backend/` и весь набор `tests/`. Production-код и тесты в ходе review не
менялись.

## Результат проверки

- PHPUnit: `41 tests, 60 assertions`, успешно.
- PHP syntax lint: успешно для всех PHP-файлов `backend/` и `tests/`.
- `make` отсутствует в Windows-среде. Эквиваленты Makefile успешно выполнены
  в контейнере `backend`: PHPUnit и `php -l`.
- `git diff -- backend tests` пуст. Этот файл является артефактом review.

## REQ и AC: трассировка на тесты

| REQ | AC | Конкретное покрытие | Оценка |
| --- | --- | --- | --- |
| REQ-MILEAGE-01 | AC-01: 399999, approve-LTV -> approve | `DecisionEngineTest::testDecidesByLtvWhenMileageIsWithinLimit`, dataset `под порогом, LTV-approve`; `AssessmentServiceTest::testKeepsLtvApproveAtMileage399999` | Покрыто |
| REQ-MILEAGE-01 | AC-02: 400000, approve-LTV -> approve | unit dataset `ровно на пороге 400000, LTV-approve`; `AssessmentServiceTest::testKeepsLtvApproveAtMileage400000` | Покрыто |
| REQ-MILEAGE-01 | AC-03: 399999, review-LTV -> review | `DecisionEngineTest::testDecidesByLtv`, datasets 72.3 и 85.0; `AssessmentServiceTest::testSendsMiddleLtvToReviewWithZeroLimit` с mileage 96000 | Покрыто для пробега ниже порога. Точная комбинация 399999 + review-LTV не выделена, но все `<=400000` проходят единую ветку. |
| REQ-MILEAGE-02 | AC-04: 400001, approve-LTV -> review | unit dataset `первое значение за порогом 400001, LTV-approve понижен`; `AssessmentServiceTest::testDowngradesLtvApproveToReviewAtMileage400001` | Покрыто |
| REQ-MILEAGE-02 | AC-05: 500000, approve-LTV -> review | unit dataset `верхняя граница валидации 500000, LTV-approve понижен`; `AssessmentServiceTest::testDowngradesLtvApproveToReviewAtMileage500000` | Покрыто. Пробел первого review устранён. |
| REQ-MILEAGE-03 | AC-06: 400001, review-LTV -> review | unit dataset `за порогом, LTV-review остаётся review`; `AssessmentServiceTest::testKeepsLtvReviewAtMileage400001` | Покрыто. Пробел первого review устранён. |
| REQ-MILEAGE-03 | AC-07: 400001, LTV >85% -> reject | unit dataset `за порогом, LTV-reject не смягчается`; `AssessmentServiceTest::testDoesNotSoftenLtvRejectAtHighMileage` | Покрыто |
| REQ-MILEAGE-04 | AC-08: исходные три LTV-зоны | `DecisionEngineTest::testDecidesByLtv`: 28.5/45.0 -> approve, 72.3/85.0 -> review, 85.01/120.0 -> reject; три базовых service-теста | Покрыто |
| REQ-MILEAGE-05 | AC-09: 400001 + approve-LTV -> review, limit 0 | `AssessmentServiceTest::testDowngradesLtvApproveToReviewAtMileage400001` | Покрыто |
| REQ-MILEAGE-06 | AC-10: старое сохранённое approve не пересчитывается | Автотеста нет; обоснование приведено ниже | Осознанный непокрытый интеграционный gap |
| Поведение вне нового правила | AC-11: mileage=0 -> решение по LTV | `AssessmentServiceTest::testApprovesLowLtvAtZeroMileage` проверяет LTV 50.0, approve и limit 450000 | Покрыто. Пробел первого review устранён. |
| Существующее поведение вне scope | AC-12: absent/null -> ValidationException | `testRejectsApplicationWithoutMileageField`; `testRejectsApplicationWithNullMileage` | Покрыто |
| Существующее поведение вне scope | AC-13: `''` -> 0 | Не тестируется намеренно по plan | Допустимо: поведение не менялось и вне scope. |

## Бизнес-поведение

### Границы и LTV

| Пробег | Проверенное поведение |
| --- | --- |
| 399999 | approve при LTV 50.0 |
| 400000 | approve при LTV 50.0, граница включительна |
| 400001 | approve -> review; review остаётся review; reject остаётся reject |
| 500000 | валидный вход; approve -> review |

`DecisionEngine::decide()` сначала вычисляет решение LTV, затем возвращает
`review` только при условиях `APPROVE` и `$mileage > $reviewMileageKm`.
Поэтому границы 399999 / 400000 / 400001 соблюдены.

Пороги LTV не изменены: `<60` -> approve, `60..85` -> review, `>85` ->
reject. `backend/config/rules.php` по-прежнему содержит `approve_max = 60.0`,
`review_max = 85.0` и `max_mileage_km = 500000`.

Reject по LTV не смягчается. `AssessmentServiceTest::testDoesNotSoftenLtvRejectAtHighMileage`
проверяет LTV 95.0, mileage 400001, `REJECT` и `approved_limit = 0`.

### Валидация и лишние требования

`ApplicationValidator` сохраняет диапазон `0..500000`; отсутствующее поле или
`null` дают ошибку. Известное преобразование `''` в 0 сохранено, как требует
intent. Реализация не добавляет валидацию, пересчёт истории, изменение schema,
фронтенд или расчёт лимита по возрасту.

`approved_limit` равен запрошенной сумме только при approve и равен 0 для
review/reject, включая review из-за пробега. AC-09 подтверждён тестом.

## AC-MILEAGE-10: обоснованный интеграционный gap

AC-10 нельзя корректно покрыть существующей инфраструктурой без изменения
production/schema/integration scope. В `tests/` есть только unit-тесты;
`tests/Feature/` содержит только README. Нет тестовой DB, фабрики PDO,
изолированного HTTP-приложения или fixture-механизма, который подготовил бы
состояние до введения правила.

Production-анализ подтверждает требование:

- `ApplicationRepository::save()` один раз записывает `ltv`, `decision` и
  `approved_limit` в `decisions` через `INSERT`.
- `ApplicationRepository::find()` и `listApplications()` только читают готовые
  значения из `decisions`; расчёт через `AssessmentService` там не вызывается.
- В production-коде нет `UPDATE decisions`, миграции или фонового пересчёта.

Следовательно, старое сохранённое approve останется approve. Автотест не
придумывается: для него потребовалось бы добавить DB/HTTP integration scope,
что запрещено условиями проверки. Это осознанный непокрытый интеграционный gap,
а не дефект unit-покрытия.

## Тесты и расхождения с планом

Тесты не ослаблены: базовые LTV-ожидания остались конкретными `assertSame`; не
обнаружены `skip`, `markTestIncomplete`, ослабленные утверждения или изменённые
пороги. Доработка добавила acceptance-тесты, не заменяя старые.

Закрыты замечания первого review:

- AC-05: `testDowngradesLtvApproveToReviewAtMileage500000`.
- AC-06: `testKeepsLtvReviewAtMileage400001`.
- AC-11: `testApprovesLowLtvAtZeroMileage`.

Осталось формальное, не блокирующее расхождение с `plan_MILEAGE.md`:

| План | Фактическое состояние | Оценка |
| --- | --- | --- |
| Обязательные `int $reviewMileageKm` и `int $mileage` | Значения по умолчанию: `400000` и `?int $mileage = null` | Production-путь всегда передаёт mileage из валидатора, поэтому бизнес-поведение корректно. Но доменный API допускает вызов без mileage и применяет чистый LTV, что слабее контракта плана. |
| Старый LTV provider передаёт 100000 | `testDecidesByLtv()` вызывает `decide($ltv)` | Не ослабляет проверки LTV, но следует из optional API и отклоняется от плана. |

Остальные пункты plan выполнены: конфигурационный порог добавлен,
`AssessmentService` передаёт mileage, `AppFactory` передаёт конфигурационный
порог, а AC-05 и AC-06 добавлены на service-уровне.

## Verdict

**ACCEPT с одним осознанным непокрытым интеграционным gap AC-MILEAGE-10 и
формальным отклонением API от плана.**

Реализация соответствует intent/spec в production-потоке: границы 399999 /
400000 / 400001 соблюдены, 500000 остаётся валидным входом, approve при большом
пробеге понижается до review, review не меняется, reject по LTV не смягчается,
а лимит не-approve равен 0. Ранее выявленные AC-05, AC-06 и AC-11 закрыты
конкретными тестами.
