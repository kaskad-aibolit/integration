# Быстрая настройка автоматизации синхронизации цен

## ✅ Уже готово:
- [x] Artisan команда `prices:sync-all` создана
- [x] Планировщик настроен на ежедневное выполнение в 12:00  
- [x] Команда зарегистрирована и видна в `php artisan schedule:list`

## 🔧 Осталось сделать:

### 1. Настроить cron job на сервере

**Для Linux/Unix серверов:**
```bash
# Открыть crontab
crontab -e

# Добавить строку (замените путь на ваш)
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**Для Windows (ваш текущий случай):**
1. Откройте "Планировщик задач" (Task Scheduler)
2. Создайте новую задачу:
   - Имя: "Laravel Price Sync"
   - Триггер: Ежедневно в 00:00 (или любое время)
   - Действие: Запуск программы
   - Программа: `cmd`
   - Аргументы: `/c "cd F:\kaskad\integration && php artisan schedule:run"`

### 2. Тестирование

```bash
# Ручной запуск для проверки
php artisan prices:sync-all --force

# Проверка планировщика
php artisan schedule:list

# Тест одного цикла планировщика
php artisan schedule:run
```

### 3. Мониторинг

```bash
# Просмотр логов синхронизации
Get-Content storage/logs/laravel.log | Select-String "СИНХРОНИЗАЦИЯ" | Select-Object -Last 10

# Просмотр cron логов
Get-Content storage/logs/sync-prices-cron.log
```

## 🚀 Готово!

После настройки cron job система будет автоматически синхронизировать цены каждый день в 12:00.

## 📊 Что происходит при синхронизации:

1. Проходит по всем элементам смарт-процессов
2. Извлекает товарные позиции (product rows)  
3. Получает актуальные цены из каталога Битрикс
4. Сравнивает и обновляет цены при необходимости
5. Ведет детальные логи процесса
6. Генерирует статистику выполнения

## ⚙️ Настройка расписания

Можно изменить время выполнения в `app/Console/Kernel.php`:

```php
// Текущая настройка: каждый день в 12:00
->dailyAt('12:00')

// Другие варианты:
->dailyAt('06:00')        // каждый день в 6:00
->hourly()                // каждый час  
->everyThirtyMinutes()    // каждые 30 минут
->weeklyOn(1, '12:00')    // каждый понедельник в 12:00
``` 