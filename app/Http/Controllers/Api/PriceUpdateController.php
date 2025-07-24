<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\PriceService;

class PriceUpdateController extends Controller
{
    protected $priceService;

    public function __construct(PriceService $priceService)
    {
        $this->priceService = $priceService;
    }

    public function priceHandle(Request $request)
    {
        $id = $request->input('data')['FIELDS']['ID'];
        try {
            // Временно возвращаем успешный ответ
            // return response()->json(['result' => 'Price update request received successfully']);
            
            // Или раскомментируйте, если PriceService готов:
            $result = $this->priceService->handlePrice($id);
            return response()->json(['result' => 'Price update processed successfully', 'price_id' => $id]);
        } catch (\Throwable $e) {
            Log::error('Price update error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function registerPriceUpdateHandler(Request $request)
    {
        // $handlerUrl = config('app.url') . '/api/bitrix/price-update';
        $handlerUrl = 'http://10.3.4.2:8001/api/bitrix/price-update';
        log::info('handlerUrl: ' . $handlerUrl);
        try {
            $result = $this->priceService->registerPriceUpdateHandler($handlerUrl);
            return response()->json(['result' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Отписывается от событий обновления цен в Битрикс
     */
    public function unregisterPriceUpdateHandler(Request $request)
    {
        $handlerUrl = "https://cnadmindemo.dynamicov.com/api/webhook/bitrix24/33";
        log::info('Отписка от событий, handlerUrl: ' . $handlerUrl);
        
        try {
            $result = $this->priceService->unregisterPriceUpdateHandler($handlerUrl);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'handler_url' => $handlerUrl,
                'data' => $result
            ], $result['success'] ? 200 : 400);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка при отписке от событий: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка при отписке от событий: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получает список всех зарегистрированных событий
     */
    public function getRegisteredEvents(Request $request)
    {
        try {
            Log::info('Запрос списка зарегистрированных событий');
            
            $result = $this->priceService->getRegisteredEvents();
            
            return response()->json([
                'success' => $result['success'],
                'data' => $result
            ], $result['success'] ? 200 : 400);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка при получении событий: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Ошибка при получении событий: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Синхронизирует все цены между смарт-процессами и каталогом товаров
     */
    public function syncAllPrices(Request $request)
    {
        try {
            Log::info('Запуск синхронизации всех цен через API');
            
            $result = $this->priceService->syncAllPrices();
            
            return response()->json([
                'success' => true,
                'message' => 'Синхронизация цен завершена успешно',
                'data' => $result
            ], 200);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка при синхронизации цен: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Ошибка при синхронизации цен: ' . $e->getMessage()
            ], 500);
        }
    }
}