<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;

class PositionsService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }

    /**
     * Получить список позиций
     */
    public function get(): array
    {

        $headers = $items = [];

        $headers = [
            ["name" => "Фото", "key" => 'image'],
            ["name" => "Артикул", "key" => 'art'],
            ["name" => "Запрос", "key" => 'query'],
            ["name" => "Товаров на WB", "key" => 'price'],
            ["name" => date('d.m'), "key" => 'count_1'],
            ["name" => date('d.m', strtotime('-1 days')), "key" => 'count_2'],
            ["name" => date('d.m', strtotime('-2 days')), "key" => 'count_3'],
        ];

        for ($i=0; $i < 6; $i++) { 
            $item = [
                "image" => "/images/17849231-1.png",
                "art" => "78858215",
                "query" => "Футболка женская овер",
                "price" => "55 492",
                "count_1" => rand(10, 100),
                "count_2" => rand(10, 100),
                "count_3" => rand(10, 100),
            ];
            $items[] = $item;
        }

        return [
            'headers' => $headers,
            'items' => $items
        ];
    }

}
