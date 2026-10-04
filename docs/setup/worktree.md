# Worktree

## Список worktree

Вывод `git worktree list`:

```text
C:/Users/user/carmoney-lab 57220b2 [d1/1.2.1-1.2.3-KozlovOleg97]
C:/Users/user/carmoney-lab/.kilo/worktrees/unit-tests 57220b2 [unit-tests]

## Ответ агента из второй сессии

Агент просмотрел тесты в `tests/Unit/`:

- `LtvCalculatorTest.php` — проверяет расчёт LTV и некорректные входные значения.
- `DecisionEngineTest.php` — проверяет решения approve / review / reject по LTV.
- `AssessmentServiceTest.php` — проверяет сквозную оценку заявки.
- `ApplicationValidatorTest.php` — проверяет валидацию входных данных заявки.

Агент работал в папке:

`C:\Users\user\carmoney-lab\.kilo\worktrees\unit-tests`

Ветка:

`unit-tests`

## Почему используется worktree

Два агента в одной папке и на одной ветке могут одновременно изменять одни и те же файлы и конфликтовать друг с другом.

Worktree создаёт отдельную копию репозитория и отдельную ветку, поэтому агенты могут работать параллельно независимо.