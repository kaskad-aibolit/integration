<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PriceService;
use Illuminate\Support\Facades\Log;

class SyncAllPricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:sync-all {--force : Принудительный запуск без подтверждения}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизирует все цены между смарт-процессами и каталогом товаров Битрикс';

    /**
     * Price service instance
     */
    protected $priceService;

    /**
     * Create a new command instance.
     */
    public function __construct(PriceService $priceService)
    {
        parent::__construct();
        $this->priceService = $priceService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== АВТОМАТИЧЕСКАЯ СИНХРОНИЗАЦИЯ ЦЕН ===');
        $this->info('Время запуска: ' . now()->format('Y-m-d H:i:s'));
        
        Log::info('=== ЗАПУСК АВТОМАТИЧЕСКОЙ СИНХРОНИЗАЦИИ ЦЕН ===');
        Log::info('Команда запущена в: ' . now()->format('Y-m-d H:i:s'));
        Log::info('Запущено через: ' . (app()->runningInConsole() ? 'Console/Cron' : 'Web'));
        
        // Проверяем параметр --force
        if (!$this->option('force') && app()->runningInConsole()) {
            if (!$this->confirm('Запустить синхронизацию всех цен? Это может занять длительное время.')) {
                $this->info('Синхронизация отменена пользователем.');
                Log::info('Синхронизация отменена пользователем');
                return Command::FAILURE;
            }
        }
        
        try {
            $this->info('Начинаем синхронизацию цен...');
            $this->newLine();
            
            // Запускаем синхронизацию
            $result = $this->priceService->syncAllPrices();
            
            // Выводим результаты в консоль
            $this->displayResults($result);
            
            // Логируем результаты
            Log::info('=== АВТОМАТИЧЕСКАЯ СИНХРОНИЗАЦИЯ ЗАВЕРШЕНА ===');
            Log::info('Результат: ' . json_encode($result['statistics'], JSON_PRETTY_PRINT));
            
            return Command::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->error('Ошибка при синхронизации цен: ' . $e->getMessage());
            $this->error('Подробности в логах приложения');
            
            Log::error('ОШИБКА ПРИ АВТОМАТИЧЕСКОЙ СИНХРОНИЗАЦИИ ЦЕН');
            Log::error('Сообщение: ' . $e->getMessage());
            Log::error('Файл: ' . $e->getFile() . ':' . $e->getLine());
            Log::error('Стек: ' . $e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Отображает результаты синхронизации в консоли
     */
    private function displayResults(array $result)
    {
        $stats = $result['statistics'];
        
        $this->info('🎉 Синхронизация завершена успешно!');
        $this->newLine();
        
        // Создаем таблицу с результатами
        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Время выполнения', $result['execution_time'] . ' сек'],
                ['Обработано элементов', $stats['totalItems']],
                ['Обработано товарных позиций', $stats['totalProductRows']],
                ['Проверено цен в каталоге', $stats['checkedPrices']],
                ['Обновлено цен', $stats['updatedPrices']],
                ['Пропущено элементов', $stats['skippedItems']],
                ['Ошибок', $stats['errors']],
            ]
        );
        
        // Вычисляем процент обновлений
        if ($stats['checkedPrices'] > 0) {
            $updatePercentage = round(($stats['updatedPrices'] / $stats['checkedPrices']) * 100, 2);
            $this->info("📊 Процент обновленных цен: {$updatePercentage}%");
        }
        
        // Выводим предупреждения, если есть ошибки
        if ($stats['errors'] > 0) {
            $this->warn("⚠️  Обнаружено {$stats['errors']} ошибок. Проверьте логи для деталей.");
        }
        
        $this->newLine();
        $this->info('Подробные логи доступны в storage/logs/laravel.log');
    }
}
