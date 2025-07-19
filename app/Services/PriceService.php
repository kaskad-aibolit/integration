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

    public function handlePrice()
    {
        // Получаем основной элемент смарт-процесса
        $res = $this->bitrixService->call(
            'crm.item.get',
            [
                'id' => 406,
                'entityTypeId' => 1078,
            ]
        );

        // Получаем товары, связанные с этим элементом
        $entityTypeId = 1078; // или получаем динамически
        $ownerType = $this->getOwnerType($entityTypeId);
        $itemId = 406; // id элемента

        $productRows = $this->bitrixService->call(
            'crm.item.productrow.list',
            [
                'filter' => [
                    '=ownerType' => $ownerType,
                    '=ownerId' => $itemId,
                    // '=productId' => 23408,
                ]
            ]
        );
        // Получаем все элементы смарт-процесса
        $itemList = $this->bitrixService->call(
            'crm.item.list',
            [
                'entityTypeId' => 1078,

            ]
        );


        // Получаем цену товара отдельным запросом
        $priceRes = $this->bitrixService->call(
            'catalog.price.list',
            [
                'filter' => [
                    'productId' => 23810,
                ]
            ]
        );

        Log::info('itemList: ' . json_encode($itemList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::info('priceRes: ' . json_encode($priceRes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::info('res: ' . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::info('productRows: ' . json_encode($productRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function registerPriceUpdateHandler($handlerUrl)
    {
        log::info('registerPriceUpdateHandler: ' . $handlerUrl);
        $this->bitrixService->call('event.unbind', [
            'event' => 'CATALOG.PRICE.ON.UPDATE',
            'handler' => $handlerUrl,
        ]);
        $res = $this->bitrixService->call('event.bind', [
            'event' => 'ONCRMDYNAMICITEMUPDATE',
            'handler' => $handlerUrl,
        ]);
        log::info('registerPriceUpdateHandler: ' . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // log::info('registerPriceUpdateHandler: ' . $handlerUrl);
        // $this->bitrixService->call('event.unbind', [
        //     'event' => 'CATALOG.PRICE.ON.UPDATE',
        //     'handler' => $handlerUrl,
        // ]);
        // $res = $this->bitrixService->call('event.bind', [
        //     'event' => 'CATALOG.PRICE.ON.UPDATE',
        //     'handler' => $handlerUrl,
        // ]);
        // log::info('registerPriceUpdateHandler: ' . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function setupBitrix()
    {
        // ... ваша логика по настройке Bitrix ...
        $handlerUrl = 'http://10.3.4.2:8001/api/bitrix/price-update';
        $this->registerPriceUpdateHandler($handlerUrl);
    }

    // Функция для вычисления ownerType по entityTypeId
    private function getOwnerType($entityTypeId)
    {
        return 'T' . strtolower(dechex($entityTypeId));
    }
}
