<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;

use App\Support\Auth;

class BuyOutService
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
        '-1' => 'Черновик',
        '' => 'Запланированно',
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
        '11' => 'В процессе',
        '228' => 'Оплачено',
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
        'Черновик' => 'Запланировано|plan',
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
            SELECT t.`group`, t.`status`, COUNT(*) AS plan, 0 AS fact, '' as class FROM client t
            WHERE t.task1 = ? AND mp = 'wb'
            ".$extSql."
            GROUP BY t.`group`
        ";

        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$task1] );
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sql = "
            SELECT * FROM client t
            WHERE t.task1 = ? AND mp = 'wb' AND  t.group = ? AND status IN ('2','3','4','5','6','7','8')";
        $sql1 = "
            SELECT * FROM client t
            WHERE t.task1 = ? AND mp = 'wb' AND  t.group = ?";
        $stmt = $dbh->prepare( $sql );
        $stmt1 = $dbh->prepare( $sql1 );
        foreach ($items as &$v){

            $stmt->execute( [$task1, $v['group']] );
            $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $v['fact'] = count($counts);
            $v['date'] = date('d.m.Y', $v['group']);

            $stmt1->execute( [$task1, $v['group']] );
            $cns = $stmt1->fetchAll(PDO::FETCH_ASSOC);
            $v['plan'] = count($cns);

            if ( $_status = $this->calcStatus( $v['status'] ) ) {
                $v['status'] = $_status;
            }
            if($v['plan'] != $v['fact'] && $v['status'] == 'Оплачено|succses'){
                $v['status'] = 'В процессе|process';
            }
        }







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




    public function dbUpdate( array $params = [] ): array
{

    $dbh = $this->rateDB->connection;
    $sql = "SELECT  COUNT(*)  FROM client";
    $stmt = $dbh->prepare( $sql );
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$value) {
        if( is_numeric ($value["status"] )){
            $sql = 'INSERT INTO client (`group`, `status`, `mp`, `type`, `article`, `size`, `search_key`, `barcode`, `sex`, `kto_zabirat`, `brand`, `naming`, `price`, `grafik_otziv`, `in_work`, `task1`, `rating_otziv`, `date_add`) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() )';
            $stmt = $dbh->prepare($sql);
            $stmt->execute( [ $value["group"], $value["status"], $value["mp"], $value["type"], $value["article"], $value["size"], $value["search_key"], $value["barcode"], $value["sex"], $value["kto_zabirat"], $value["brand"], $value["naming"], $value["price"], $value["grafik_otziv"], $value["in_work"], $value["task1"], $value["rating_otziv"]] );
            $sql = 'DELETE FROM client WHERE id = ?';
            $stmt = $dbh->prepare($sql);
            $stmt->execute([$value["id"]]);
        }

    }
}

    private function getByGroupMain ( string $group, array $params ): array
{
    $dbh = $this->rateDB->connection;
    $statusKeys = [
        '-1' => 'Черновик',
        '0' => 'Черновик',
        '' => 'Запланированно',
        '1' => 'Запланированно',
        '2' => 'Оплачено',
        '3' => 'Оплачено',
        '4' => 'Оплачено',
        '5' => 'Оплачено',
        'согласовать' => 'Оплачено',
        '6' => 'Оплачено',
        '7' => 'Оплачено',
        '8' => 'Оплачено',
        '9' => 'Не оплачено',
        '228' => 'Оплачено',
        '10' => 'Не оплачено',
        '11' => 'В процессе',
        'no-money' => 'Недостаточно средств',
    ];




    $statuses = [
        'Черновик' => 'Запланировано|plan',
        'Запланированно' => 'Запланировано|plan',
        'Готово' => 'Готово|succses',
        'Оплачено' => 'Оплачено|succses',
        'Не оплачено' => 'Не оплачено|dunger',
        'В процессе' => 'В процессе|process',
        'Недостаточно средств' => 'Недостаточно средств|dunger',
    ];
    $task1 = @Auth::user()['task1'];
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
            ["name" => "Баркод", "key" => 'barcode', "text" => "Баркод", "value" => 'barcode', 'sortable' => false],
            ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query', 'sortable' => false],
            ["name" => "Пол", "key" => 'gender', "text" => "Пол", "value" => 'gender', 'sortable' => false],
            ["name" => "Дата выкупа", "key" => 'date_buy', "text" => "Дата выкупа", "value" => 'date_buy', 'sortable' => false]

        ];


        $sql = "
                SELECT 
                    id,
                    status,
                    brand,
                    article AS art,
                    price,
                    size,
                    barcode,
                    img_wb as image,
                    search_key AS query,
                    '-1' AS `position`,
                   sex AS gender,
                   date_buy,
                   '' AS `copy`,
                   '' AS `del`
                 FROM client 
                WHERE `group` = ? AND mp = 'wb' AND `task1` = ?
            ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$group, $task1] );

        $cache = [];
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);






       foreach ($items as &$item) {
            $item['status'] = $statuses[$statusKeys[$item['status']]];
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
            ["text" => "Кол-во план", "value" => 'plan', 'sortable' => false],
            ["text" => "Кол-во факт", "value" => 'fact', 'sortable' => false],
           ];
        // $headers[] = ["text" => "Дата выкупа", "value" => 'date', 'sortable' => false];

        $sql = "
                SELECT 
                    ct.brand,
                    ct.article AS art,
                    ct.price,
                    ct.size,
                    ct.img_wb as image,
                    ct.status,
                    GROUP_CONCAT( CONCAT(ct.status,'|', if ( ct.date_buy IS NOT NULL, ct.date_buy, If( ct.grafik IS NOT NULL, ct.grafik)) ) SEPARATOR ';') as sd,
                    GROUP_CONCAT( DISTINCT ct.id  SEPARATOR ';') as ids,
                    COUNT(*) AS `plan`,
                    ct.search_key AS query,
                   ct.sex AS gender,
                   '' AS `copy`,
                   '' AS `del`
                 FROM client ct
                WHERE ct.group = ? AND mp = 'wb' AND ct.task1 = ?
                GROUP BY ct.article
            ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$group, $task1]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $_statuses = [];

        $sql1 = "
                SELECT 
                  *
                 FROM client ct
                WHERE ct.group = ? AND ct.article = ? AND mp = 'wb' AND ct.task1 = ? AND status IN(2,3,4,5,6,7,8)
            ";


        $stmt1 = $dbh->prepare( $sql1 );







        foreach ($items as &$item) {

            $item['can_be_edited'] = false;



            $item['ids'] = explode(';', $item['ids']);
            $item['ids'] = array_map(function($v){
                return (int) $v;
            }, $item['ids']);

            $item['status'] = $statuses[$statusKeys[$item['status']]];
            unset($item['sd']);



            $item['gender_opt'] = [
                ['value' => '', "text" => "Нет"],
                ['value' => 'm', "text" => "М"],
                ['value' => 'w', "text" => "Ж"],
            ];


            $stmt1->execute( [$group, $item['art'], $task1] );

            $cnt = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            $item['fact'] = count($cnt);
            if($item['plan'] != $item['fact']){
                $item['status'] = 'В процессе|process';
            }
        }

        $_statuses = array_unique($_statuses);
        $_statuses = array_values($_statuses);

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
                    ct.status as sd,
                    if ( ct.date_buy IS NOT NULL, ct.date_buy, If( ct.grafik IS NOT NULL, ct.grafik, ct.grafik_otziv )) as date,
                    GROUP_CONCAT( DISTINCT ct.id  SEPARATOR ';') as ids,
                    COUNT(*) AS `plan`,
                    0 as `fact`,
                    ct.search_key AS query
                 FROM client ct
                WHERE ct.`group` = ? ct.`task1` = ? AND mp = 'wb' 
                GROUP BY date, ct.search_key
            ";
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$group, $task1] );

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cache = [];



        foreach ($items as &$item) {
            $item['status'] = 'Запланированно|plan';
            $item['can_be_edited'] = false;

            $item['ids'] = explode(';', $item['ids']);
            $item['ids'] = array_map(function($v){
                return (int) $v;
            }, $item['ids']);

            $_statuses = [];
            $sd_exp = explode(';', $item['sd']);
            unset($item['sd']);

            $_statuses = array_unique($_statuses);
            $_statuses = array_values($_statuses);

            if(!$item['status']){
                $item['status'] = 'Запланированно|plan';
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
             FROM client ct
            WHERE ct.`group` = ? AND mp = 'wb'
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
                        *
                     FROM client ct
                    WHERE ct.`group` = ? AND mp = 'wb'
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
                $item['status'] = $statuses[$item['status']];
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
                       *
                     FROM client ct
                    WHERE ct.`group` = ? AND mp = 'wb'
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
                     FROM client ct
                    WHERE ct.`group` = ? AND mp = 'wb'
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
                 FROM client ct
                WHERE ct.`group` = ? AND mp = 'wb'
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


    $result_2 =  $this->getByGroupMain( $group, $params );

    if ( count($result_2['items']) > 0 ) {
        return $result_2;
    }


}
}
