# Проверка формы заявки на Android-эмуляторе (mobile MCP)

Дата проверки: 2026-10-09
Устройство: `emulator-5554` (sdk_gphone64_x86_64, Android, mobile MCP).
Браузер: установленный в эмуляторе Chrome (`com.android.chrome`).
Сервис: бэкенд `carmoney-lab` в Docker, доступ из эмулятора по `http://10.0.2.2:8080/`.

## Шаги

1. **Подключение и выбор таргета.**
   - `mobile device list` → виден `emulator-5554`.
   - `mobile device set_target android`.

2. **Запуск браузера и навигация.**
   - `mobile system shell am start -a android.intent.action.VIEW -d http://10.0.2.2:8080/` —
     открыл Chrome и сразу перешёл на `http://10.0.2.2:8080/`. Главная отрисовалась
     (`Предварительная оценка заявки — carmoney-lab`).
   - Chrome при первом запуске показал экраны «Sign in» и «Chrome notifications»;
     их закрыл через «Stay signed out» / «No thanks» (только служебные экраны
     Chrome, к логике формы отношения не имеют).

3. **Форма заявки доступна на главной странице** — отдельной страницы формы нет,
   поля сразу под заголовком. Список полей: `applicant_ref`, `vin`, `year`,
   `mileage`, `market_value`, `requested_amount`, `term_months`; кнопки
   «Отправить заявку» и «Только рассчитать LTV».

4. **Замечание про ввод текста в WebView.**
   - `mobile input tap` на поле `mileage` и `mobile input text "400001"`
     **не приводили к изменению значения** поля в Chrome WebView: атрибут
     `text` в uiautomator-dump продолжал показывать плейсхолдер
     `Пробег, км`, а не новое значение. Координаты и сам фокус при этом
     выставлялись корректно.
   - Прямой `adb shell input text "400001"` в Chrome WebView тоже не дал
     эффекта.
   - Чтобы выполнить задачу, я подключился к Chrome через
     `adb forward tcp:9222 localabstract:chrome_devtools_remote` и
     `Runtime.evaluate` по DevTools-протоколу:
     - нашёл таб `http://10.0.2.2:8080/`,
     - `document.getElementById('mileage').value = '400001'`,
     - проверил остальные поля (`applicant_ref=CL-0025`, `vin=XTA21099998765432`,
       `year=2019`, `market_value=900000`, `requested_amount=450000`,
       `term_months=24`) — все валидны.
   - **В рамках задачи ввод через DevTools — это обход проблемы, а не
     «обычный ввод пользователя с клавиатуры эмулятора».** Если требовать
     именно эмуляторную клавиатуру, шаг 4 (установка пробега 400001 через
     mobile-ввод) считать неуспешным.

5. **Отправка заявки.** Также через DevTools:
   `document.getElementById('application-form').requestSubmit()`. Запрос
   `POST /api/applications` прошёл без сетевых ошибок (статус 2xx).

6. **Проверка результата (как видит пользователь).**
   - Через `Runtime.evaluate`: панель `#result` показана, `#errors` скрыта.
     Текст, который увидел пользователь:
     - **Решение: REVIEW**
     - LTV: 50 %
     - Возраст авто: 7 лет
     - Одобренный лимит: 0 ₽
     - Номер заявки: 31
   - Это совпадает с тем, что отрендерил Chrome: в uiautomator-dump
     видны строки `REVIEW`, `50 %`, `7 лет`, `0 ₽`, `31`.

7. **Скриншот.** `adb exec-out screencap -p` после отправки формы
   сохранён в `docs/hw2/mobile_check.png` (314 КБ, показывает форму
   с заполненным `mileage=400001` и панель результата со значением REVIEW).

## Итог

- Заявка отправлена, сервис обработал её успешно.
- Решение системы: **REVIEW** (понижение с approve из-за пробега свыше
  `review_mileage_km = 400000` км, см. `backend/config/rules.php:25`),
  LTV = 50 % (это значение попадает в диапазон approve_max 60 %
  по LTV, см. `backend/config/rules.php:46`, поэтому без правила
  пробега заявка была бы `approve`).
- Шаг «установить пробег 400001 вводом с клавиатуры эмулятора через mobile MCP»
  штатными средствами `mobile input text` не сработал в Chrome WebView;
  значение установлено через Chrome DevTools, и это задокументировано
  явно, без попытки выдать это за обычный ввод пользователя.
