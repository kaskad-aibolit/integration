# Автоматизация синхронизации цен

## Обзор

Система автоматической синхронизации цен между смарт-процессами и каталогом товаров Битрикс.

## Компоненты

### 1. Artisan команда
- **Команда:** `php artisan prices:sync-all`
- **Параметры:** `--force` (пропускает подтверждение)
- **Описание:** Синхронизирует все цены между смарт-процессами и каталогом

### 2. Планировщик Laravel
- **Расписание:** Каждый день в 12:00
- **Настройка:** `app/Console/Kernel.php`
- **Логи:** `storage/logs/sync-prices-cron.log`

## Настройка на сервере

### Шаг 1: Настройка cron job

Добавьте следующую строку в crontab сервера:

```bash
# Редактируем crontab
crontab -e

# Добавляем строку (замените /path/to/your/project на реальный путь)
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**Для Windows (Планировщик задач):**
1. Откройте "Планировщик задач"
2. Создайте новую задачу
3. Триггер: Ежедневно
4. Действие: `cmd /c "cd F:\kaskad\integration && php artisan schedule:run"`

### Шаг 2: Проверка настройки

```bash
# Проверить список команд
php artisan list | grep prices

# Ручной запуск для тестирования
php artisan prices:sync-all

# Принудительный запуск без подтверждения
php artisan prices:sync-all --force

# Проверить планировщик
php artisan schedule:list
```

### Шаг 3: Мониторинг

#### Логи автоматических запусков:
```bash
# Основные логи приложения
tail -f storage/logs/laravel.log

# Специальные логи cron задач
tail -f storage/logs/sync-prices-cron.log
```

#### Поиск записей синхронизации:
```bash
grep "СИНХРОНИЗАЦИЯ" storage/logs/laravel.log
grep "CRON:" storage/logs/laravel.log
```

## Варианты расписания

В файле `app/Console/Kernel.php` можно изменить расписание:

```php
// Каждый день в 12:00 (текущая настройка)
$schedule->command('prices:sync-all --force')->dailyAt('12:00');

// Каждый час
$schedule->command('prices:sync-all --force')->hourly();

// Каждый день в 6:00 утра
$schedule->command('prices:sync-all --force')->dailyAt('06:00');

// Каждый понедельник в 12:00
$schedule->command('prices:sync-all --force')->weeklyOn(1, '12:00');

// Каждое 1 число месяца в 12:00
$schedule->command('prices:sync-all --force')->monthlyOn(1, '12:00');

// Каждые 30 минут
$schedule->command('prices:sync-all --force')->everyThirtyMinutes();
```

## Ручное управление

### Запуск команды
```bash
# Интерактивный режим (спросит подтверждение)
php artisan prices:sync-all

# Автоматический режим (без подтверждения)
php artisan prices:sync-all --force
```

### API endpoints
```bash
# Ручной запуск через API
POST http://127.0.0.1:8001/api/sync-all-prices

# Отписка от событий Битрикс
POST http://127.0.0.1:8001/api/unregister-price-update-handler

# Массовая отписка
POST http://127.0.0.1:8001/api/unregister-all-price-update-handlers

# Проверка событий
GET http://127.0.0.1:8001/api/get-registered-events
```

## Мониторинг и отладка

### Проверка статуса
```bash
# Посмотреть последние запуски
grep "АВТОМАТИЧЕСКАЯ СИНХРОНИЗАЦИЯ" storage/logs/laravel.log | tail -10

# Проверить ошибки
grep "ERROR.*СИНХРОНИЗАЦ" storage/logs/laravel.log

# Статистика последнего запуска
grep -A 20 "ФИНАЛЬНАЯ СТАТИСТИКА" storage/logs/laravel.log | tail -25
```

### Отключение автоматизации
Закомментируйте строки в `app/Console/Kernel.php`:
```php
// $schedule->command('prices:sync-all --force')
//     ->dailyAt('12:00')
//     ...
```

## Безопасность и производительность

- Команда использует `withoutOverlapping()` - предотвращает запуск параллельных процессов
- `runInBackground()` - запуск в фоновом режиме
- Увеличенный лимит времени выполнения (10 минут)
- Детальное логирование всех операций
- Обработка ошибок и исключений

## Уведомления об ошибках

Для настройки уведомлений при ошибках добавьте в планировщик:

```php
$schedule->command('prices:sync-all --force')
    ->dailyAt('12:00')
    ->onFailure(function () {
        // Отправка email при ошибке
        Mail::to('admin@example.com')->send(new SyncFailedMail());
    });
``` 