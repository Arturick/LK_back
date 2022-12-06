<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;

use \DatePeriod;
use \DateTime;
use \DateInterval;

use App\Support\Auth;

class DeliveryService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService,
        private FindService $fаindService
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }

    private function calcStatus( $value ) {

        $statusKeys = [
            'ожидает' => 'Ожидает',
            '2' => 'Оплачено',
            '4' => 'Забран',
            'no-money' => 'Недостаточно средств',
        ];

        foreach ($statusKeys as $_key => $_value) {
            if ( preg_match('#'.$value.'#ui', $_key) ) {
                $value = $_value;
                break;
            }
        }

        $statuses = [
            'Ожидает' => 'Ожидает получения|plan',
            '3' => 'Ожидает получения|plan',
            '2' => 'Ожидает получения|plan',
            '4' => 'Получено|succses',
            '5' => 'Получено|succses',
            '6' => 'Получено|succses',
            '7' => 'Получено|succses',
            '8' => 'Получено|succses',
            'Недостаточно средств' => 'Недостаточно средств|dunger',
        ];


        return @$statuses[ $value ];
    }



    /**
     * Список сгруппированных выкупов
     */
    public function list( array $params = [] ): array
    {
        $statuses = [

            '3' => 'Ожидает получения|plan',
            '2' => 'Ожидает получения|plan',
            '4' => 'Получено|succses',
            '5' => 'Получено|succses',
            '6' => 'Получено|succses',
            '7' => 'Получено|succses',
            '8' => 'Получено|succses',

        ];
        $type = @$params['type'];

        // $task1 = 30570069;
        $task1 = @Auth::user()['task1'];


        $dbh = $this->rateDB->connection;

        $pvz_opt = [];

        if ( $type == 'im' ) {
            $headers = [
                ["text" => "Фото", "value" => 'image', 'sortable' => false],
                ["text" => "Артикул", "value" => 'art', 'sortable' => false],
                ["text" => "Цвет", "value" => 'color', 'sortable' => false],
                ["text" => "Размер", "value" => 'size', 'sortable' => false],
                ["text" => "ФИО", "value" => 'fio', 'sortable' => false],
                ["text" => "ПВЗ", "value" => 'pvz', 'sortable' => false],
                ["text" => "Статус", "value" => 'status', 'sortable' => false],
                ["text" => "Код", "value" => 'code', 'sortable' => false],
            ];

            $cache = [];


            $sql = "
                SELECT
                    '' AS `image`,
                    cl.article AS art,
                    '' AS color,
                    cl.size,
                    CONCAT(cl.name, ' ',cl.surname) AS fio,
                    cl.punkt_vidachi AS pvz,
                    cl.`status`,
                    cl.group,
                    cl.code
                FROM client cl WHERE  cl.task1 = ?
                AND cl.mp = 'wb'
                 GROUP BY t.`group`
            ";

            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [$task1] );

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pvz_opt = array_map(function( $v ){
                return ['value' => $v['pvz'], 'text' => $v['pvz']];
            }, $items);
            array_unshift( $pvz_opt, ['value' => '', 'text' => 'Не выбранно']);


            // $items = [];
            // $items[] = [
            //     'image' => '',
            //     'art' => '78858215',
            //     'color' => 'Розовый',
            //     'size' => 'XS',
            //     'fio' => 'Ивано Иван Иванович',
            //     'pvz' => 'г Москва, Улица Покрышкина 8к2',
            //     'status' => 'Готов к выдаче',
            //     'code' => '148',
            // ];

            foreach ($items as &$item) {
                if ( @$cache[$item['art']] ) {
                    $_item = $cache[$item['art']];
                } else {
                    $_result = $this->fаindService->findByArt(['art' => $item['art']]);
                    $_item = @$_result['items'][0];
                    $cache[$item['art']] = $_item;
                }

                $item['image'] = @$_item['image'];
            }
            unset($item);
            unset($_item);
        }

        if ( $type == 'forme' ) {
            $headers = [
                ["text" => "Дата", "value" => 'date', 'sortable' => false],
                ["text" => "План", "value" => 'plan', 'sortable' => false],
                ["text" => "Получено товаров", "value" => 'count', 'sortable' => false],
                ["text" => "Статус", "value" => 'status', 'sortable' => false],
                ["text" => "", "value" => 'action', 'sortable' => false],
            ];


            $sql = "
                SELECT
                  DATE(cl.grafik) AS `date`,
                  COUNT(*) plan,
                  cl.status,
                  img_wb AS image,
                  cl.group AS `group`
                FROM `client` cl WHERE cl.task1 = ? AND cl.mp = 'wb' AND  status IN ('2','3','4','5','6','7','8')
            ";

            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [$task1] );

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as &$item) {
                if(array_key_exists($item['status'],  $statuses)){
                    $item['status'] = $statuses[$item['status']];
                } else {
                    $item['status'] = 'Ожидает получения|plan';
                }
                $sql = "
                SELECT
                  *
                FROM `client` cl WHERE cl.task1 = ? AND cl.group = ? AND cl.mp = 'wb' AND  status IN ('4','5','6','7','8')
            ";

                $stmt = $dbh->prepare( $sql );
                $stmt->execute( [$task1, $item['group']] );
                $cnt = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $item['count'] = count($cnt);

                if($item['count'] != $item['plan']){
                    $item['status'] = 'В процессе|process';
                }
            }


        }

        return [
            'headers' => $headers,
            'items' => $items,
            'pvz_opt' => $pvz_opt
        ];
    }




    public function getByGroup( string $group, array $params ): array
    {

        $dbh = $this->rateDB->connection;
        $task1 = @Auth::user()['task1'];
        $statuses = [
            '3' => 'Ожидает получения|plan',
            '2' => 'Ожидает получения|plan',
            '4' => 'Получено|succses',
            '5' => 'Получено|succses',
            '6' => 'Получено|succses',
            '7' => 'Получено|succses',
            '8' => 'Получено|succses',

        ];
        $output = [];
        $output['success'] = false;

        $headers = [
            ["text" => "Фото", "value" => 'image', 'sortable' => false],
            ["text" => "Артикул", "value" => 'art', 'sortable' => false],
            ["text" => "Цвет", "value" => 'color', 'sortable' => false],
            ["text" => "Размер", "value" => 'size', 'sortable' => false],
            ["text" => "Дата покупки", "value" => 'date_buy', 'sortable' => false],
            ["text" => "Чек", "value" => 'cheque', 'sortable' => false],
            ["text" => "Дата получения", "value" => 'date_get', 'sortable' => false],
            ["text" => "Статус", "value" => 'status', 'sortable' => false],
        ];

        $sql = "
            SELECT
              img_wb AS image,
              '' AS color,
             article AS art,
              status,
              size,
              date_buy,
              date_get,
              `check` AS cheque
            FROM client  WHERE `task1` = ? AND `group` = ? AND status IN ('3','4','5','6','7','8')";

        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$task1, $group] );

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cache = [];

        foreach ($items as &$item) {

            $item['status'] = $statuses[$item['status']];
        }


        $output['headers'] = $headers;
        $output['items'] = $items;

        return $output;
    }
}
