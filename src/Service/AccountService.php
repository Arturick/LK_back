<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;
use \DatePeriod;
use \DateTime;
use \DateInterval;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use App\Support\Auth;

class AccountService
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


    // Смотрите, в выкупах, всего 4 статуса
    // 1 Запланировано - желтый цвет
    // 2 Оплачено - зелёный
    // 3 Не оплачено - красный
    // 4 В процессе - синий

    // Остальные статусы, мы тут не отображаем, так как не зачем, так как они отображаются в доставках и отзывах.

    // то есть в базе данных у нас если следующие статсы в выкупах мы их приравниваем.

    // корзина собирается - в процессе, но статус "в процессе сохраняется только в течении заявленной даты выкупа, если по фактической дате выкупа, у товара статус не сменился, то на сайте мы его меняем на "не оплачено"
    // оплачено - оплачено
    // получить- оплачено
    // получено - оплачено
    // согласовать - оплачено
    // опубликовать - оплачено
    // модерация - оплачено
    // опубликован - оплачено
    // Отмена- не оплачено
    // Возврат - не оплачено

    // в заданиях которые в промежуточной таблице, на них ставиться статус запланировано и эти строки можно редактировать

    $statusKeys = [
        'черновик' => 'Черновик',
        '1' => 'В процессе',
        '2' => 'Оплачено',
        '3' => 'Оплачено',
        '4' => 'Оплачено',
        '5' => 'Оплачено',
        'согласовать' => 'Оплачено',
        '6' => 'Оплачено',
        '7' => 'Оплачено',
        '8' => 'Оплачено',
        '9' => 'Не оплачено',
        '10' => 'Не оплачено',
        'no-money' => 'Недостаточно средств',
    ];


    foreach ($statusKeys as $_key => $_value) {
        if ( preg_match('#'.$value.'#ui', $_key) ) {
            $value = $_value;
            break;
        }
    }

    $statuses = [
        'Черновик' => 'Черновик|plan',
        'Запланировано' => 'Запланировано|plan',
        'Готово' => 'Готово|succses',
        'Оплачено' => 'Оплачено|succses',
        'Не оплачено' => 'Не оплачено|dunger',
        'В процессе' => 'В процессе|process',
        'Недостаточно средств' => 'Недостаточно средств|dunger',
    ];


    return @$statuses[ $value ];
}


/**
 * Список сгруппированных выкупов
 */
public function list( array $params = [] ): array
    {

        // $task1 = 144;
        $task1 = @Auth::user()['task1'];
        if ( @$task1 ) $task1 = str_replace('#', '', $task1);

        $dbh = $this->rateDB->connection;
        $stmt = $dbh->prepare('SET SESSION group_concat_max_len = 1000000');
        $stmt->execute();

        $model = @$params['model'];
        if ( !$model ) $model = 'm1';
        if ( !in_array($model, ['m1', 'm2']) ) $model = 'm1';

        $headers = $items = [];

        $extSql = [];
        if ( $model == "m1" ) {
            $extSql[] = 'AND t.kto_zabirat = "RATE-THIS"';
        } else if ( $model == "m2" ) {
            $extSql[] = 'AND (t.kto_zabirat != "RATE-THIS" OR t.kto_zabirat IS NULL)';
        }

        $headers = [
            ["text" => "Заявка от", "value" => 'date', 'sortable' => false],
            ["text" => "Заказов план", "value" => 'plan', 'sortable' => false],
            ["text" => "Заказов факт", "value" => 'fact', 'sortable' => false],
            ["text" => "Статус", "value" => 'status', 'sortable' => false],
            ["text" => "", "value" => 'actions', 'sortable' => false],
        ];

        $extSql = implode("\n", $extSql);

        $sql = "
            SELECT t.`group`, t.`status`, COUNT(*) AS plan, 0 AS fact, '' as class FROM client_temp t
            WHERE t.task1 = ?
            ".$extSql."
            GROUP BY t.`group`
            ORDER BY cast(t.`group` as unsigned) DESC
        ";

        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$task1] );
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function( $v ){
            if ( $_status = $this->calcStatus( $v['status'] ) ) {
                $v['status'] = $_status;
            }

            $v['date'] = date('d.m.Y', $v['group']);

            return $v;
        }, $items);
        //
        //


        $sql = "
            SELECT t.`group`, COUNT(*) AS plan, 0 AS fact, GROUP_CONCAT(t.`status`) as statusGroup, '' as class FROM client_dev t
            WHERE t.task1 = ?
                AND t.`group` IS NOT NULL
             GROUP BY t.`group`
        ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$task1] );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = array_map(function($v){

            $status = [];
            $exp = explode(',', $v['statusGroup']);

            $_i = 0;
            $_no_money = 0;

            foreach ($exp as $e) {
                if ( $_status = $this->calcStatus( $e ) ) {
                    $status[] = $_status;
                    if ( preg_match('#оплачено#ui', $_status) ) {
                        ++$_i;
                    }

                    if ( preg_match('#Недостаточно средств#ui', $_status) ) {
                        ++$_no_money;
                    }

                }

            }

            $v['fact'] = $_i;

            $status = array_unique($status);
            $status = array_values($status);

            if ( count($status) > 1 ) {
                if ( $_no_money > 0 ) {
                    $v['class'] = 'tr-dunger';
                    $v['status'] = $this->calcStatus( 'no-money' );
                } else {
                    $v['status'] = $this->calcStatus( 'В процессе' );
                }
            } else {

                if ( preg_match('#Оплачено#ui', $status[0]) ) {
                    $v['status'] = $this->calcStatus( 'Готово' );
                } else {
                    $v['status'] = $this->calcStatus( 'В процессе' );
                }

            }


            $v['date'] = date('d.m.Y', $v['group']);
            return $v;
        }, $rows);


        $items = array_merge($items, $rows );

        usort($items, function ($a, $b) {
            if ($a["date"] == $b["date"]) {
                return 0;
            }
            return (strtotime($a["date"]) < strtotime($b["date"])) ? -1 : 1;
        });

        return [
            'headers' => $headers,
            'items' => $items
        ];
    }

    private function getByGroupMain ( string $group, array $params ): array
{
    $dbh = $this->rateDB->connection;
    // 1- По артикулам
    // 2- По датам
    // 3- Все (базовая)

    $sort = $params['sort'];
    $show_actions = false;
    $items = [];

    $_no_money = 0;

    if ( $sort == 3 ) {
        $headers = [
            ["name" => "Фото", "key" => 'image', "text" => "Фото", "value" => 'image', 'sortable' => false],
            ["name" => "Бренд", "key" => 'brand', "text" => "Бренд", "value" => 'brand', 'sortable' => false],
            ["name" => "Артикул", "key" => 'art', "text" => "Артикул", "value" => 'art', 'sortable' => false],
            ["name" => "Цена WB", "key" => 'price', "text" => "Цена WB", "value" => 'price', 'sortable' => false],
            ["name" => "Размер", "key" => 'size', "text" => "Размер", "value" => 'size', 'sortable' => false],
            ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
            ["name" => "Пол", "key" => 'gender', "text" => "Пол", "value" => 'gender', 'sortable' => false],
        ];
        $headers[] = ["text" => "Дата выкупа", "value" => 'date', 'sortable' => false];

        $sql = "
                SELECT 
                    ct.id,
                    ct.status,
                    ct.brand,
                    ct.article AS art,
                    ct.price,
                    ct.size,
                    ct.barcode,
                    if ( ct.date_buy IS NOT NULL, ct.date_buy, If( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv )) as date,
                    ct.search_key AS query,
                    '-1' AS `position`,
                   ct.sex AS gender,
                   '' AS `copy`,
                   '' AS `del`
                 FROM client_dev ct
                WHERE ct.`group` = ?
            ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$group] );

        $cache = [];
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);



        foreach ($items as &$item) {
            if ( @$cache[$item['art']] ) {
                $_item = $cache[$item['art']];
            } else {
                $_result = $this->fаindService->findByArt(['art' => $item['art']]);
                $_item = @$_result['items'][0];
                $cache[$item['art']] = $_item;
            }

            if ( !@$item['brand'] && @$_item['brand'] ) $item['brand'] = $_item['brand'];

            $item['status'] = $this->calcStatus( $item['status'] );

            if ( preg_match('#Недостаточно средств#ui',$item['status']) ) {
                ++$_no_money;
            }

            $item['image'] = @$_item['image'];
            $item['price'] = @$_item['price'];
            $item['sizes'] = @$_item['sizes'];
            $item['size_opt'] = @$_item['sizes'];
            $item['gender_opt'] = [
                ['value' => '', "text" => "Нет"],
                ['value' => 'm', "text" => "М"],
                ['value' => 'w', "text" => "Ж"],
            ];
        }

        if ( $_no_money > 0 ) {
            $items = array_map(function( $v ){
                if ( preg_match('#Не оплачено#ui', $v['status']) ) {
                    $v['status'] = $this->calcStatus( 'no-money' );
                }
                return $v;
            }, $items);
        }

    }

    if ( $sort == 1 ) {

        $headers = [
            ["name" => "Фото", "key" => 'image', "text" => "Фото", "value" => 'image', 'sortable' => false],
            ["name" => "Бренд", "key" => 'brand', "text" => "Бренд", "value" => 'brand', 'sortable' => false],
            ["name" => "Артикул", "key" => 'art', "text" => "Артикул", "value" => 'art', 'sortable' => false],
            ["name" => "Цена WB", "key" => 'price', "text" => "Цена WB", "value" => 'price', 'sortable' => false],
            ["name" => "Размер", "key" => 'size', "text" => "Размер", "value" => 'size', 'sortable' => false],
            ["name" => "Баркод", "key" => 'barcode', "text" => "Баркод", "value" => 'barcode', 'sortable' => false],
            ["text" => "Кол-во план", "value" => 'plan', 'sortable' => false],
            ["text" => "Кол-во факт", "value" => 'fact', 'sortable' => false],
            ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
            ["name" => "Пол", "key" => 'gender', "text" => "Пол", "value" => 'gender', 'sortable' => false],
        ];
        // $headers[] = ["text" => "Дата выкупа", "value" => 'date', 'sortable' => false];

        $sql = "
                SELECT 
                    ct.brand,
                    ct.article AS art,
                    ct.price,
                    ct.size,
                    ct.barcode,
                    GROUP_CONCAT( CONCAT(ct.status,'|', if ( ct.date_buy IS NOT NULL, ct.date_buy, If( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv )) ) SEPARATOR ';') as sd,
                    GROUP_CONCAT( DISTINCT ct.id  SEPARATOR ';') as ids,
                    COUNT(*) AS `plan`,
                    0 as `fact`,
                    ct.search_key AS query,
                   ct.sex AS gender,
                   '' AS `copy`,
                   '' AS `del`
                 FROM client_dev ct
                WHERE ct.`group` = ?
                GROUP BY ct.article, ct.search_key
            ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$group] );

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cache = [];

        $_statuses = [];

        foreach ($items as &$item) {

            $item['can_be_edited'] = false;

            if ( @$cache[$item['art']] ) {
                $_item = $cache[$item['art']];
            } else {
                $_result = $this->fаindService->findByArt(['art' => $item['art']]);
                $_item = @$_result['items'][0];
                $cache[$item['art']] = $_item;
            }

            $item['ids'] = explode(';', $item['ids']);
            $item['ids'] = array_map(function($v){
                return (int) $v;
            }, $item['ids']);

            $sd_exp = explode(';', $item['sd']);
            foreach ($sd_exp as $_sd) {
                $_sd = explode('|', $_sd);
                $status = $_sd[0];
                $status = $this->calcStatus($status);
                $_statuses[] = $status;
            }
            unset($item['sd']);

            $item['image'] = @$_item['image'];
            $item['price'] = @$_item['price'];
            $item['sizes'] = @$_item['sizes'];
            $item['size_opt'] = @$_item['sizes'];
            $item['gender_opt'] = [
                ['value' => '', "text" => "Нет"],
                ['value' => 'm', "text" => "М"],
                ['value' => 'w', "text" => "Ж"],
            ];
        }

        $_statuses = array_unique($_statuses);
        $_statuses = array_values($_statuses);

        if ( count($_statuses) > 1 ) {
            $items = array_map(function($v){
                $v['status'] = 'В процессе|process';
                return $v;
            }, $items);
        }

    }

    if ( $sort == 2 ) {

        $headers = [
            ["text" => "Дата выкупа", "value" => 'date', 'sortable' => false],
            ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
            ["text" => "Кол-во план", "value" => 'plan', 'sortable' => false],
            ["text" => "Кол-во факт", "value" => 'fact', 'sortable' => false],
        ];

        $sql = "
                SELECT 
                    GROUP_CONCAT( CONCAT(ct.status,'|', if ( ct.date_buy IS NOT NULL, ct.date_buy, If( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv )) ) SEPARATOR ';') as sd,
                    if ( ct.date_buy IS NOT NULL, ct.date_buy, If( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv )) as date,
                    GROUP_CONCAT( DISTINCT ct.id  SEPARATOR ';') as ids,
                    COUNT(*) AS `plan`,
                    0 as `fact`,
                    ct.search_key AS query
                 FROM client_dev ct
                WHERE ct.`group` = ?
                GROUP BY date, ct.search_key
            ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$group] );

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cache = [];



        foreach ($items as &$item) {

            $item['can_be_edited'] = false;

            $item['ids'] = explode(';', $item['ids']);
            $item['ids'] = array_map(function($v){
                return (int) $v;
            }, $item['ids']);

            $_statuses = [];
            $sd_exp = explode(';', $item['sd']);
            foreach ($sd_exp as $_sd) {
                $_sd = explode('|', $_sd);
                $status = $_sd[0];
                $status = $this->calcStatus($status);
                $_statuses[] = $status;
            }
            unset($item['sd']);

            $_statuses = array_unique($_statuses);
            $_statuses = array_values($_statuses);


            if ( count($_statuses) > 1 ) {
                $item['status'] = 'В процессе|process';
            } else {
                $item['status'] = $_statuses[0];
            }
        }





    }

    $headers[] = ["text" => "Статус", "value" => 'status', 'sortable' => false];

    if ( $show_actions ) {
        $headers[] = ["name" => "", "key" => 'copy', "value" => 'copy', 'sortable' => false];
    }
    $headers[] = ["name" => "", "key" => 'del', "value" => 'del', 'sortable' => false];


    if ( $sort == 1 || $sort == 2 ) {
        $headers[] = ["text" => "", "value" => 'action', 'sortable' => false];
    }

    return [
        'headers' => $headers,
        'items' => $items,
        'date' => date('d.m.Y', $group),
        'show_actions' => $show_actions,
        'no_money' => ( $_no_money > 0 )
    ];
}

    private function getByGroupTemp ( string $group, array $params ): array
{
    $dbh = $this->rateDB->connection;

    // 1- По артикулам
    // 2- По датам
    // 3- Все (базовая)

    $sort = $params['sort'];

    $show_actions = true;

    $_no_money = 0;

    $items = [];
    $headers = [
        ["name" => "Фото", "key" => 'image', "text" => "Фото", "value" => 'image', 'sortable' => false],
        ["name" => "Бренд", "key" => 'brand', "text" => "Бренд", "value" => 'brand', 'sortable' => false],
        ["name" => "Артикул", "key" => 'art', "text" => "Артикул", "value" => 'art', 'sortable' => false],
        ["name" => "Цена WB", "key" => 'price', "text" => "Цена WB", "value" => 'price', 'sortable' => false],
        ["name" => "Размер", "key" => 'size', "text" => "Размер", "value" => 'size', 'sortable' => false],
        ["name" => "Баркод", "key" => 'barcode', "text" => "Баркод", "value" => 'barcode', 'sortable' => false],
        ["name" => "Кол-во выкупов", "key" => 'count', "text" => "Кол-во выкупов", "value" => 'count', 'sortable' => false],
        ["name" => "Кол-во отзывов", "key" => 'rcount', "text" => "Кол-во отзывов", "value" => 'rcount', 'sortable' => false],
        ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
        ["name" => "Позиция", "key" => 'position', "text" => "Позиция", "value" => 'position', 'sortable' => false],
        ["name" => "Пол", "key" => 'gender', "text" => "Пол", "value" => 'gender', 'sortable' => false],
    ];


    $sql = "
            SELECT 
                ct.status
             FROM client_temp ct
            WHERE ct.`group` = ?
        ";
    $stmt = $dbh->prepare( $sql );
    $stmt->execute( [$group] );
    $status = $stmt->fetchColumn();

    if ( $status == 'Заявка' ) {
        $show_actions = false;

        if ( $sort == 3 ) {
            $headers = [
                ["name" => "Фото", "key" => 'image', "text" => "Фото", "value" => 'image', 'sortable' => false],
                ["name" => "Бренд", "key" => 'brand', "text" => "Бренд", "value" => 'brand', 'sortable' => false],
                ["name" => "Артикул", "key" => 'art', "text" => "Артикул", "value" => 'art', 'sortable' => false],
                ["name" => "Цена WB", "key" => 'price', "text" => "Цена WB", "value" => 'price', 'sortable' => false],
                ["name" => "Размер", "key" => 'size', "text" => "Размер", "value" => 'size', 'sortable' => false],
                ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
                ["name" => "Пол", "key" => 'gender', "text" => "Пол", "value" => 'gender', 'sortable' => false],
            ];
            $headers[] = ["text" => "Дата выкупа", "value" => 'date', 'sortable' => false];

            $sql = "
                    SELECT 
                        ct.id,
                        ct.status,
                        ct.brand,
                        ct.article AS art,
                        ct.price,
                        ct.size,
                        ct.barcode,
                        if ( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv ) as date,
                        ct.search_key AS query,
                        '-1' AS `position`,
                       ct.sex AS gender,
                       '' AS `copy`,
                       '' AS `del`
                     FROM client_temp ct
                    WHERE ct.`group` = ?
                ";
            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [$group] );

            $cache = [];
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$item) {
                if ( @$cache[$item['art']] ) {
                    $_item = $cache[$item['art']];
                } else {
                    $_result = $this->fаindService->findByArt(['art' => $item['art']]);
                    $_item = @$_result['items'][0];
                    $cache[$item['art']] = $_item;
                }

                if ( strtotime($item['date']) <= time() ) {
                    $item['status'] = 'Не оплачено';
                    $item['can_be_edited'] = false;
                }
                $item['status'] = $this->calcStatus( $item['status'] );

                $item['image'] = @$_item['image'];
                $item['price'] = @$_item['price'];
                $item['sizes'] = @$_item['sizes'];
                $item['size_opt'] = @$_item['sizes'];
                $item['gender_opt'] = [
                    ['value' => '', "text" => "Нет"],
                    ['value' => 'm', "text" => "М"],
                    ['value' => 'w', "text" => "Ж"],
                ];
            }
        }
        if ( $sort == 1 ) {

            $headers = [
                ["name" => "Фото", "key" => 'image', "text" => "Фото", "value" => 'image', 'sortable' => false],
                ["name" => "Бренд", "key" => 'brand', "text" => "Бренд", "value" => 'brand', 'sortable' => false],
                ["name" => "Артикул", "key" => 'art', "text" => "Артикул", "value" => 'art', 'sortable' => false],
                ["name" => "Цена WB", "key" => 'price', "text" => "Цена WB", "value" => 'price', 'sortable' => false],
                ["name" => "Размер", "key" => 'size', "text" => "Размер", "value" => 'size', 'sortable' => false],
                ["name" => "Баркод", "key" => 'barcode', "text" => "Баркод", "value" => 'barcode', 'sortable' => false],
                ["text" => "Кол-во план", "value" => 'plan', 'sortable' => false],
                ["text" => "Кол-во факт", "value" => 'fact', 'sortable' => false],
                ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
                ["name" => "Пол", "key" => 'gender', "text" => "Пол", "value" => 'gender', 'sortable' => false],
            ];
            // $headers[] = ["text" => "Дата выкупа", "value" => 'date', 'sortable' => false];

            $sql = "
                    SELECT 
                        ct.brand,
                        ct.article AS art,
                        ct.price,
                        ct.size,
                        ct.barcode,
                        GROUP_CONCAT( CONCAT(ct.status,'|',if ( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv ) ) SEPARATOR ';') as sd,
                        GROUP_CONCAT( DISTINCT ct.id  SEPARATOR ';') as ids,
                        COUNT(*) AS `plan`,
                        0 as `fact`,
                        ct.search_key AS query,
                       ct.sex AS gender,
                       '' AS `copy`,
                       '' AS `del`
                     FROM client_temp ct
                    WHERE ct.`group` = ?
                    GROUP BY ct.article, ct.search_key
                ";
            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [$group] );

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cache = [];

            $_statuses = [];

            foreach ($items as &$item) {

                $item['can_be_edited'] = false;


                if ( @$cache[$item['art']] ) {
                    $_item = $cache[$item['art']];
                } else {
                    $_result = $this->fаindService->findByArt(['art' => $item['art']]);
                    $_item = @$_result['items'][0];
                    $cache[$item['art']] = $_item;
                }

                $item['ids'] = explode(';', $item['ids']);
                $item['ids'] = array_map(function($v){
                    return (int) $v;
                }, $item['ids']);

                $sd_exp = explode(';', $item['sd']);
                foreach ($sd_exp as $_sd) {
                    $_sd = explode('|', $_sd);
                    if ( strtotime($_sd[1]) <= time() ) {
                        $status = 'Не оплачено';
                    }
                    $status = $this->calcStatus($status);

                    $_statuses[] = $status;
                }
                unset($item['sd']);

                $item['image'] = @$_item['image'];
                $item['price'] = @$_item['price'];
                $item['sizes'] = @$_item['sizes'];
                $item['size_opt'] = @$_item['sizes'];
                $item['gender_opt'] = [
                    ['value' => '', "text" => "Нет"],
                    ['value' => 'm', "text" => "М"],
                    ['value' => 'w', "text" => "Ж"],
                ];
            }

            $_statuses = array_unique($_statuses);
            $_statuses = array_values($_statuses);

            if ( count($_statuses) > 1 ) {
                $items = array_map(function($v){
                    $v['status'] = 'В процессе|process';
                    return $v;
                }, $items);
            }

        }

        if ( $sort == 2 ) {

            $headers = [
                ["text" => "Дата выкупа", "value" => 'date', 'sortable' => false],
                ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
                ["text" => "Кол-во план", "value" => 'plan', 'sortable' => false],
                ["text" => "Кол-во факт", "value" => 'fact', 'sortable' => false],
            ];

            $sql = "
                    SELECT 
                        GROUP_CONCAT( CONCAT(ct.status,'|',if ( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv ) ) SEPARATOR ';') as sd,
                        if ( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv ) as date,
                        GROUP_CONCAT( DISTINCT ct.id  SEPARATOR ';') as ids,
                        COUNT(*) AS `plan`,
                        0 as `fact`,
                        ct.search_key AS query
                     FROM client_temp ct
                    WHERE ct.`group` = ?
                    GROUP BY date, ct.search_key
                ";
            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [$group] );

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cache = [];



            foreach ($items as &$item) {

                $item['can_be_edited'] = false;

                $item['ids'] = explode(';', $item['ids']);
                $item['ids'] = array_map(function($v){
                    return (int) $v;
                }, $item['ids']);

                $_statuses = [];
                $sd_exp = explode(';', $item['sd']);
                foreach ($sd_exp as $_sd) {
                    $_sd = explode('|', $_sd);
                    if ( strtotime($_sd[1]) <= time() ) {
                        $status = 'Не оплачено';
                    }
                    $status = $this->calcStatus($status);

                    $_statuses[] = $status;
                }
                unset($item['sd']);

                $_statuses = array_unique($_statuses);
                $_statuses = array_values($_statuses);


                if ( count($_statuses) > 1 ) {
                    $item['status'] = 'В процессе|process';
                } else {
                    $item['status'] = $_statuses[0];
                }
            }





        }


    }

    if ( $status == 'Черновик' ) {
        $sql = "
                SELECT 
                    ct.status,
                    ct.brand,
                    ct.article AS art,
                    ct.price,
                    ct.size,
                    ct.barcode,
                    if ( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv ) as date,
                    SUM(IF (ct.`type` = 'выкуп', 1, 0)) AS `count`,
                    SUM(IF (ct.`type` = 'отзыв', 1, 0)) AS `rcount`,
                    ct.search_key AS query,
                    '-1' AS `position`,
                   ct.sex AS gender,
                   '' AS `copy`,
                   '' AS `del`
                 FROM client_temp ct
                WHERE ct.`group` = ?
                GROUP BY ct.article, ct.search_key
            ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$group] );

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cache = [];

        foreach ($items as &$item) {

            if ( !@$item['gender'] ) $item['gender'] = '';

            $item['can_be_edited'] = true;

            if ( $item['status'] == 'Черновик' ) {
                $item['status'] = 'Черновик|plan';
            }

            if ( @$cache[$item['art']] ) {
                $_item = $cache[$item['art']];
            } else {
                $_result = $this->fаindService->findByArt(['art' => $item['art']]);
                $_item = @$_result['items'][0];
                $cache[$item['art']] = $_item;
            }

            $item['count'] = $item['count'] + $item['rcount'];

            $item['image'] = @$_item['image'];
            $item['price'] = @$_item['price'];
            $item['sizes'] = @$_item['sizes'];
            $item['size_opt'] = @$_item['sizes'];
            $item['gender_opt'] = [
                ['value' => '', "text" => "Нет"],
                ['value' => 'm', "text" => "М"],
                ['value' => 'w', "text" => "Ж"],
            ];
        }

    }


    $headers[] = ["text" => "Статус", "value" => 'status', 'sortable' => false];

    if ( $show_actions ) {
        $headers[] = ["name" => "", "key" => 'copy', "value" => 'copy', 'sortable' => false];
    }
    $headers[] = ["name" => "", "key" => 'del', "value" => 'del', 'sortable' => false];


    if ( $sort == 1 || $sort == 2 ) {
        $headers[] = ["text" => "", "value" => 'action', 'sortable' => false];
    }

    return [
        'headers' => $headers,
        'items' => $items,
        'date' => date('d.m.Y', $group),
        'show_actions' => $show_actions,
        'no_money' => ( $_no_money > 0 )
    ];
}


    public function getByGroup( string $group, array $params ): array
{

    $result_1 =  $this->getByGroupTemp( $group, $params );
    $result_2 =  $this->getByGroupMain( $group, $params );

    if ( count($result_2['items']) > 0 ) return $result_2;
    if ( count($result_1['items']) > 0 ) return $result_1;
}
}
