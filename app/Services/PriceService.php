<?php

namespace App\Services;

use App\Services\Bitrix\BitrixService;
use Illuminate\Support\Facades\Log;

class PriceService
{
    protected $bitrixService;

    public function __construct(BitrixService $bitrixService)
    {
        $this->bitrixService = $bitrixService;
    }

    public function handlePrice($id)
    {
        // Засекаем время начала выполнения
        $startTime = microtime(true);
        Log::info("Начинаем обработку price update для ID: {$id} в " . date('Y-m-d H:i:s'));

        
        
        // Увеличиваем лимит времени выполнения до 5 минут для обработки большого количества данных
        set_time_limit(300); // 300 секунд = 5 минут
        ini_set('max_execution_time', 300);
        
        // Получаем основной элемент смарт-процесса
        $entityTypeId = 1078; // или получаем динамически
        $ownerType = $this->getOwnerType($entityTypeId);
        log::info('id: ' . $id);
        
        // Получаем информацию о цене
        $priceRes = $this->bitrixService->call(
            'catalog.price.list',
            [
                'filter' => [
                    'id' => $id,
                ]
            ]
        );
        $test = $this->bitrixService->call(
            'crm.item.productrow.get',
            [
                'productId' => 23949
            ]
        );
        log::info('test: ' . json_encode($test, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        log::info('priceRes: ' . json_encode($priceRes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Проверяем, есть ли результат и извлекаем productId
        if (empty($priceRes['result']['prices']) || !isset($priceRes['result']['prices'][0]['productId'])) {
            Log::error('No price found or productId missing for price id: ' . $id);
            $endTime = microtime(true);
            return;
        }
        
        $productId = $priceRes['result']['prices'][0]['productId'];
        Log::info('Found productId: ' . $productId . ' for price id: ' . $id);
        
        
        // Получаем элементы смарт-процесса с пагинацией и ищем те, что содержат наш productId
        $relatedItems = [];
        $start = 0;
        $limit = 50;
        $processedCount = 0;
        
        do {
            $itemList = $this->bitrixService->call(
                'crm.item.list',
                [
                    'entityTypeId' => $entityTypeId,
                    'start' => $start,
                    'limit' => $limit
                ]
            );
            
            if (empty($itemList['result']['items'])) {
                break;
            }
            
            Log::info("Проверяем страницу: start={$start}, элементов=" . count($itemList['result']['items']));
            
            // Проверяем каждый элемент на наличие нужного productId
            foreach ($itemList['result']['items'] as $item) {
                $processedCount++;
                
                // Получаем product rows для этого элемента
                $productRows = $this->bitrixService->call(
                    'crm.item.productrow.list',
                    [
                        'filter' => [
                            '=ownerType' => $ownerType,
                            '=ownerId' => $item['id'],
                        ]
                    ]
                );
                
                // Проверяем, есть ли наш productId в этом элементе
                if (!empty($productRows['result']['productRows'])) {
                    foreach ($productRows['result']['productRows'] as $row) {
                        if ($row['productId'] == $productId) {
                            $relatedItems[] = $item;
                            Log::info("Найден связанный элемент ID: " . $item['id'] . " с productId: " . $productId);
                            break; // Переходим к следующему элементу
                        }
                    }
                }
                
                // Оптимизация: если обработали много элементов без результата, можно остановиться
                if ($processedCount % 100 == 0) {
                    Log::info("Обработано {$processedCount} элементов, найдено связанных: " . count($relatedItems));
                }
            }
            
            $start += $limit;
            
        } while (isset($itemList['next']) && $itemList['next'] > 0);
        
        Log::info("Поиск завершен. Всего обработано элементов: {$processedCount}, найдено связанных: " . count($relatedItems));
             
        // Получаем новую цену из priceRes
        $newPrice = $priceRes['result']['prices'][0]['price'];
        $updatedRowsCount = 0;
        
        // Засекаем время начала обновления
        $updateStartTime = microtime(true);
        
        // Обрабатываем найденные связанные элементы
        foreach ($relatedItems as $item) {
            Log::info("Обрабатываем связанный элемент ID: " . $item['id']);
            
            // Получаем product rows для обновления цены
            $productRows = $this->bitrixService->call(
                'crm.item.productrow.list',
                [
                    'filter' => [
                        '=ownerType' => $ownerType,
                        '=ownerId' => $item['id'],
                    ]
                ]
            );
            
            if (!empty($productRows['result']['productRows'])) {
                foreach ($productRows['result']['productRows'] as $row) {
                    // Обновляем только строки с нашим productId
                    if ($row['productId'] == $productId) {
                        Log::info("Обновляем product row ID: " . $row['id'] . " с цены {$row['price']} на {$newPrice}");
                        
                        $updateResult = $this->bitrixService->call(
                            'crm.item.productrow.update',
                            [
                                'id' => $row['id'],
                                'fields' => [
                                    'productId' => $row['productId'],
                                    'price' => $newPrice,
                                    'quantity' => $row['quantity'],
                                    'discountTypeId' => $row['discountTypeId'] ?? 2,
                                    'discountRate' => $row['discountRate'] ?? 0,
                                    'taxRate' => $row['taxRate'] ?? 0,
                                    'taxIncluded' => $row['taxIncluded'] ?? 'N',
                                    'measureCode' => $row['measureCode'] ?? 796,
                                    'sort' => $row['sort'] ?? 10,
                                ]
                            ]
                        );
                        
                        if (!empty($updateResult['result'])) {
                            $updatedRowsCount++;
                            Log::info("Успешно обновлена строка ID: " . $row['id']);
                        } else {
                            Log::error("Ошибка обновления строки ID: " . $row['id'] . " - " . json_encode($updateResult));
                        }
                    }
                }
            }
        }
        
        Log::info("Обновление завершено. Обновлено product rows: {$updatedRowsCount} для productId: {$productId} с новой ценой: {$newPrice}");
        
        Log::info("=== РЕЗУЛЬТАТ ОБРАБОТКИ PRICE UPDATE ===");
        Log::info("Price ID: {$id}");
        Log::info("Product ID: {$productId}");
        Log::info("Новая цена: {$newPrice}");
        Log::info("Найдено связанных элементов: " . count($relatedItems));
        Log::info("Обновлено product rows: {$updatedRowsCount}");
    }

    /**
     * Синхронизирует цены между всеми смарт-процессами и каталогом товаров
     * Проходит по всем элементам смарт-процессов, извлекает product rows,
     * получает актуальные цены из каталога и обновляет при необходимости
     */
    public function syncAllPrices()
    {
        // Засекаем время начала выполнения
        $startTime = microtime(true);

        // Увеличиваем лимит времени выполнения до 10 минут
        set_time_limit(600); // 600 секунд = 10 минут
        ini_set('max_execution_time', 600);

        // Настройки для обработки
        $entityTypeId = 1078; // ID смарт-процесса
        $ownerType = $this->getOwnerType($entityTypeId);
        
        // Статистика
        $stats = [
            'totalItems' => 0,           // Всего элементов смарт-процесса
            'totalProductRows' => 0,     // Всего product rows
            'checkedPrices' => 0,        // Проверено цен в каталоге
            'updatedPrices' => 0,        // Обновлено цен
            'errors' => 0,               // Ошибки
            'skippedItems' => 0,         // Пропущенные элементы
        ];
        

        // Получаем все элементы смарт-процесса с пагинацией
        $start = 0;
        $limit = 50;
        $pageNumber = 1;
        
        do {
            
            // Получаем страницу элементов
            $itemList = $this->bitrixService->call(
                'crm.item.list',
                [
                    'entityTypeId' => $entityTypeId,
                    'start' => $start,
                    'limit' => $limit
                ]
            );
            
            // Проверяем результат запроса
            if (empty($itemList['result']['items'])) {
                break;
            }
            
            $itemsOnPage = count($itemList['result']['items']);
            $stats['totalItems'] += $itemsOnPage;
            
            // Обрабатываем каждый элемент на странице
            foreach ($itemList['result']['items'] as $itemIndex => $item) {
                $itemId = $item['id'];
                
                try {
                    // Получаем product rows для элемента
                    $productRows = $this->bitrixService->call(
                        'crm.item.productrow.list',
                        [
                            'filter' => [
                                '=ownerType' => $ownerType,
                                '=ownerId' => $itemId,
                            ]
                        ]
                    );
                    
                    // Проверяем наличие product rows
                    if (empty($productRows['result']['productRows'])) {
                        $stats['skippedItems']++;
                        continue;
                    }
                    
                    $rowsCount = count($productRows['result']['productRows']);
                    $stats['totalProductRows'] += $rowsCount;
                    
                    // Обрабатываем каждый product row
                    foreach ($productRows['result']['productRows'] as $rowIndex => $row) {
                        $rowId = $row['id'];
                        $productId = $row['productId'];
                        $currentPrice = $row['price'];
                        
                        
                        try {
                            // Получаем актуальную цену из каталога товаров
                            $catalogPrice = $this->bitrixService->call(
                                'catalog.price.list',
                                [
                                    'filter' => [
                                        'productId' => $productId,
                                    ],
                                    'select' => ['id', 'productId', 'price', 'priceTypeId']
                                ]
                            );
                            
                            $stats['checkedPrices']++;
                            
                            // Проверяем результат запроса цены
                            if (empty($catalogPrice['result']['prices'])) {
                                Log::warning("    Цена для ProductID={$productId} не найдена в каталоге");
                                continue;
                            }
                            
                            // Берем первую найденную цену (можно усложнить логику выбора по priceTypeId)
                            $catalogPriceData = $catalogPrice['result']['prices'][0];
                            $actualPrice = $catalogPriceData['price'];
                            $priceId = $catalogPriceData['id'];
                            
                            
                            // Сравниваем цены (используем сравнение с небольшой погрешностью для float)
                            $priceDifference = abs((float)$currentPrice - (float)$actualPrice);
                            
                            if ($priceDifference < 0.01) {
                                continue;
                            }
                            
                            
                            // Обновляем только цену в product row
                            $updateResult = $this->bitrixService->call(
                                'crm.item.productrow.update',
                                [
                                    'id' => $rowId,
                                    'fields' => [
                                        'price' => $actualPrice,
                                    ]
                                ]
                            );
                            
                            // Проверяем результат обновления
                            if (!empty($updateResult['result'])) {
                                $stats['updatedPrices']++;
                            } else {
                                $stats['errors']++;
                                Log::error("    ✗ Ошибка обновления цены: " . json_encode($updateResult));
                            }
                            
                        } catch (\Exception $e) {
                            $stats['errors']++;
                            Log::error("    ✗ Исключение при обработке Product Row {$rowId}: " . $e->getMessage());
                        }
                    }
                    
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error("✗ Исключение при обработке элемента {$itemId}: " . $e->getMessage());
                }
            }
            
            // Переходим к следующей странице
            $start += $limit;
            $pageNumber++;
            
        } while (isset($itemList['next']) && $itemList['next'] > 0);
        
        // Вычисляем время выполнения
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        // Выводим финальную статистику
        Log::info("=== СИНХРОНИЗАЦИЯ ЗАВЕРШЕНА ===");
        Log::info("Время выполнения: {$executionTime} секунд");
        Log::info("--- ФИНАЛЬНАЯ СТАТИСТИКА ---");
        Log::info("Всего обработано элементов смарт-процесса: {$stats['totalItems']}");
        Log::info("Всего обработано product rows: {$stats['totalProductRows']}");
        Log::info("Проверено цен в каталоге: {$stats['checkedPrices']}");
        Log::info("Обновлено цен: {$stats['updatedPrices']}");
        Log::info("Пропущено элементов (без товаров): {$stats['skippedItems']}");
        Log::info("Ошибок: {$stats['errors']}");
        
        if ($stats['checkedPrices'] > 0) {
            $updatePercentage = round(($stats['updatedPrices'] / $stats['checkedPrices']) * 100, 2);
            Log::info("Процент обновленных цен: {$updatePercentage}%");
        }
        
        Log::info("=== КОНЕЦ СИНХРОНИЗАЦИИ ===");
        
        return [
            'success' => true,
            'execution_time' => $executionTime,
            'statistics' => $stats
        ];
    }

    public function registerPriceUpdateHandler($handlerUrl)
    {
        log::info('registerPriceUpdateHandler: ' . $handlerUrl);
   
        // Сначала отписываемся от события, чтобы избежать дублирования
        $this->bitrixService->call('event.unbind', [
            'event' => 'CATALOG.PRICE.ON.UPDATE',
            'handler' => $handlerUrl,
        ]);
        
        // Подписываемся на событие обновления цены
        $res = $this->bitrixService->call('event.bind', [
            'event' => 'CATALOG.PRICE.ON.UPDATE',
            'handler' => $handlerUrl,
        ]);
        
        log::info('registerPriceUpdateHandler result: ' . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Проверяем текущие события для подтверждения
        $events = $this->bitrixService->call('event.get', []);
        log::info('Current events: ' . json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $res;
    }

    /**
     * Отписывается от событий обновления цен в Битрикс
     */
    public function unregisterPriceUpdateHandler($handlerUrl)
    {
        log::info('unregisterPriceUpdateHandler: ' . $handlerUrl);
        
        try {
            // Отписываемся от события обновления цены
            $res = $this->bitrixService->call('event.unbind', [
                'event' => 'ONCRMDYNAMICITEMUPDATE',
                'handler' => $handlerUrl,
            ]);
            
            log::info('unregisterPriceUpdateHandler result: ' . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Проверяем текущие события для подтверждения отписки
            $events = $this->bitrixService->call('event.get', []);
            log::info('Events after unsubscribe: ' . json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Проверяем результат операции отписки
            if (isset($res['result']['count'])) {
                $unsubscribedCount = $res['result']['count'];
                
                if ($unsubscribedCount > 0) {
                    log::info("Успешно отписались от {$unsubscribedCount} событий CATALOG.PRICE.ON.UPDATE");
                    return [
                        'success' => true,
                        'message' => "Успешно отписались от {$unsubscribedCount} событий обновления цен",
                        'handler_url' => $handlerUrl,
                        'unsubscribed_count' => $unsubscribedCount
                    ];
                } else {
                    log::info('Событие не найдено или уже было отписано');
                    return [
                        'success' => true,
                        'message' => 'Событие не найдено или уже было отписано ранее',
                        'handler_url' => $handlerUrl,
                        'unsubscribed_count' => 0
                    ];
                }
            } else {
                log::warning('Неожиданный формат ответа при отписке: ' . json_encode($res));
                return [
                    'success' => false,
                    'message' => 'Неожиданный формат ответа от Битрикс',
                    'response' => $res
                ];
            }
            
        } catch (\Exception $e) {
            log::error('Исключение при отписке от события: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Исключение при отписке: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Получает список всех зарегистрированных событий
     */
    public function getRegisteredEvents()
    {
        log::info('Получаем список зарегистрированных событий');
        
        try {
            $events = $this->bitrixService->call('event.get', []);
            log::info('Registered events: ' . json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            return [
                'success' => true,
                'events' => $events
            ];
            
        } catch (\Exception $e) {
            log::error('Ошибка при получении событий: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ошибка при получении событий: ' . $e->getMessage()
            ];
        }
    }

    public function setupBitrix()
    {
        // ... ваша логика по настройке Bitrix ...
        $handlerUrl = 'http://10.3.4.2:8002/api/bitrix/price-update';
        $this->registerPriceUpdateHandler($handlerUrl);
    }

    // Функция для вычисления ownerType по entityTypeId
    private function getOwnerType($entityTypeId)
    {
        return 'T' . strtolower(dechex($entityTypeId));
    }
}
