<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Синхронизация цен каждый час
        $schedule->command('prices:sync-all --force')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('CRON: Автоматическая синхронизация цен выполнена успешно в ' . now()->format('Y-m-d H:i:s'));
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('CRON: Ошибка при автоматической синхронизации цен в ' . now()->format('Y-m-d H:i:s'));
            })
            ->sendOutputTo(storage_path('logs/sync-prices-cron.log'))
            ->appendOutputTo(storage_path('logs/sync-prices-cron.log'));
            
        // Альтернативные варианты расписания (закомментированы):
        // $schedule->command('prices:sync-all --force')->dailyAt('12:00');          // каждый день в 12:00
        // $schedule->command('prices:sync-all --force')->dailyAt('06:00');          // каждый день в 6:00
        // $schedule->command('prices:sync-all --force')->weeklyOn(1, '12:00');      // каждый понедельник в 12:00
        // $schedule->command('prices:sync-all --force')->monthlyOn(1, '12:00');     // каждое 1 число месяца в 12:00
        // $schedule->command('prices:sync-all --force')->everyThirtyMinutes();     // каждые 30 минут
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
