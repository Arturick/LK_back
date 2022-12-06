<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;

use App\Support\Auth;

class UpdateService
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

public function dbUpdate( ){
    $extSql = [];

    $dbh = $this->rateDB->connection;
    $sql = "SELECT * FROM client_temp t";
    $stmt = $dbh->prepare( $sql );
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$value) {
        if(is_numeric($value["status"]) && (date("Y-m-d") == $value["grafik_otziv"] || date("Y-m-d") == $value["grafik"])){
            $sql = 'INSERT INTO client (`group`, `mp`, `type`, `article`, `size`, `search_key`, `barcode`, `sex`, `kto_zabirat`, `brand`, `naming`, `price_wb`, `grafik`, `in_work`, `task1`, `rating_otziv`, `date_add`) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() )';
            $stmt = $dbh->prepare($sql);
            if($value["grafik"]){
                $stmt->execute( [ $value["group"], $value["mp"], $value["type"], $value["article"], $value["size"], $value["search_key"], $value["barcode"], $value["sex"], $value["kto_zabirat"], $value["brand"], $value["naming"], $value["price"], $value["grafik"], $value["in_work"], $value["task1"], $value["rating_otziv"]] );
            }
            if($value["grafik_otziv"]){
                $stmt->execute( [ $value["group"], $value["mp"], $value["type"], $value["article"], $value["size"], $value["search_key"], $value["barcode"], $value["sex"], $value["kto_zabirat"], $value["brand"], $value["naming"], $value["price"], $value["grafik_otziv"], $value["in_work"], $value["task1"], $value["rating_otziv"]] );
            }

            $sql = 'DELETE FROM client_temp WHERE id = ?';
            $stmt = $dbh->prepare($sql);
            $stmt->execute([$value["id"]]);
        }

    }
    return [mktime(0, 0, 0, date("m")  , date("d"), date("Y"))];
}

}
