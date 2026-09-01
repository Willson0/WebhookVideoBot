# YooKassa Webhook Handler

<p align="center">
  <img src="https://img.shields.io/badge/Status-Completed-success?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Backend-PHP-777bb4?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Framework-Laravel-ff2d20?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Payments-YooKassa-0052cc?style=for-the-badge" />
  <img src="https://img.shields.io/badge/No%20UI-Backend%20Service-555555?style=for-the-badge" />
</p>

<p align="center">
  Backend-сервис для приема и обработки webhook-уведомлений от <b>YooKassa</b>.
</p>

---

## О проекте

**YooKassa Webhook Handler** — это backend-приложение на Laravel, предназначенное для приема webhook-уведомлений от платежной системы YooKassa.

Проект обрабатывает события, связанные с оплатой, отказом в платеже и другими операциями, после чего выполняет необходимые действия в соответствии с бизнес-логикой и сохраняет данные в базе данных.

---

## Основной функционал

- Прием webhook-уведомлений от YooKassa
- Обработка событий об успешной оплате
- Обработка уведомлений об отказе в оплате
- Обработка прочих платежных операций
- Валидация входящих данных
- Взаимодействие с базой данных
- Интеграция в существующую backend-инфраструктуру

---

## Технологии

- PHP
- Laravel
- MySQL / PostgreSQL
- Webhook API
- REST-интеграция

---

## Архитектура

Проект построен как серверный обработчик событий:

1. YooKassa отправляет webhook при изменении статуса платежа
2. Laravel принимает входящий запрос
3. Система проверяет и обрабатывает событие
4. Данные записываются или обновляются в базе данных
5. Выполняются необходимые бизнес-операции

---

## Установка

Проект устанавливается стандартным способом для Laravel.

### Шаги установки

```bash
composer install
cp .env.example .env
php artisan key:generat
```

Настройте подключение к базе данных и параметры YooKassa в файле `.env`.

После этого выполните миграции:

```bash
php artisan migrate
```

---

## Запуск

```bash
php artisan serve
```


Или через ваш веб-сервер / Docker, если он используется в проекте.

---

## Особенности

- Нет пользовательского интерфейса
- Сфокусирован на серверной логике
- Подходит для интеграции платежей в Laravel-проекты
- Простая и понятная структура кода
- Обработка событий в реальном времени

---

## Статус проекта

Проект завершен и используется как backend-решение для обработки платежных webhook-уведомлений.

---

## Автор

GitHub: https://github.com/Willson0

---

## Лицензия

Проект создан в учебных и демонстрационных целях.
