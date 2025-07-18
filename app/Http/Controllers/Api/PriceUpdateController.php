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
        Log::info('Bitrix price update request:', $request->all());
        try {
            $result = $this->priceService->handlePrice();
            // return response()->json(['result' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function registerPriceUpdateHandler(Request $request)
    {
        // $handlerUrl = config('app.url') . '/api/bitrix/price-update';
        $handlerUrl = 'https://cnadmindemo.dynamicov.com/api/bitrix/price-update';
        log::info('handlerUrl: ' . $handlerUrl);
        try {
            $result = $this->priceService->registerPriceUpdateHandler($handlerUrl);
            return response()->json(['result' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}