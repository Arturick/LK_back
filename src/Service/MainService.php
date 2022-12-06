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

class MainService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }

/**
 * Получить список для каруели на главной
 */
public function getFastInfo(): array
{

    $dbh = $this->rateDB->connection;

    // $client_id = 144;
    $client_id = @Auth::user()['task1'];
    if ( @$client_id ) $client_id = str_replace('#', '', $client_id);

    $items = [];

    // заказали за сегодня = все то что с статусом = оплачен+ сегодняшняя data buy
    $value = '';
    $sql = "
            SELECT COUNT(*) FROM client cl
            WHERE cl.task1 = ?
            AND (
            cl.date_buy LIKE ?
            OR cl.date_buy LIKE ?
            )
            AND cl.`status` = '2'
        ";
    $stmt = $dbh->prepare( $sql );
    if ( $stmt->execute( [ $client_id, date('Y-m-d').'%', date('d.m.Y').'%' ] ) ) {
        $value = $stmt->fetchColumn();

    }
    if ( !$value ) $value = 0;
    // $_value = '🛒 19 шт. / 6422 ₽';
    $value = '🛒 '.$value.' шт.';

    $items[] = [
        'value' => $value,
        'label' => 'Заказали сегодня',
        'link' => '',
        'help' => '',
    ];


    // забрали за вчера= все то что с статусом= получен, согалсовать, опубликовать, опубликован,модерация+ вчерашнее число date get
    $value = '';
    $sql = "
            SELECT COUNT(*) FROM client cl
            WHERE cl.task1 = ?
            AND (
            cl.date_get LIKE ?
            OR cl.date_get LIKE ?
            )
            AND cl.`status` IN ('3', 'Cогалсовать','5', '7', '6')
        ";
    $stmt = $dbh->prepare( $sql );
    if ( $stmt->execute( [ $client_id, date('Y-m-d', strtotime("-1 days")).'%', date('d.m.Y', strtotime("-1 days")).'%' ] ) ) {
        $value = $stmt->fetchColumn();

    }
    if ( !$value ) $value = 0;
    // $_value = '🚚 49 шт.';
    $value = '🚚 '.$value.' шт.';

    $items[] = [
        'value' => $value,
        'label' => 'Забрали вчера',
        'link' => '',
        'help' => '',
    ];

    // написали отзывов=Все то что со статусом модераци и опубликован за все время
    $value = '';
    $sql = "
            SELECT COUNT(*) FROM client cl
            WHERE cl.task1 = ?
            AND cl.`status` IN ('5', '7', '6')
        ";
    $stmt = $dbh->prepare( $sql );
    if ( $stmt->execute( [ $client_id ] ) ) {
        $value = $stmt->fetchColumn();

    }
    if ( !$value ) $value = 0;
    // $value = '✍️ 29 шт.';
    $value = '✍️ '.$value.' шт.';

    $items[] = [
        'value' => $value,
        'label' => 'Написали отзывов',
        'link' => '',
        'help' => '',
    ];
    $items[] = [
        'value' => '💳 0 ₽',
        'label' => 'Баланс на выкупы',
        'link' => '#',
        'link_name' => 'Пополнить',
        'help' => '#',
    ];
    $items[] = [
        'value' => '🛒 0 шт.',
        'label' => 'Доступно выкупов',
        'link' => '#',
        'link_name' => 'Заказать',
        'help' => '#',
    ];
    $items[] = [
        'value' => '⭐️ 0 шт.',
        'label' => 'Доступно отзывов',
        'link' => '#',
        'link_name' => 'Опубликовать',
        'help' => '#',
    ];
    $items[] = [
        'value' => '👤 0 ₽',
        'label' => 'Баланс ЛК',
        'link' => '#',
        'link_name' => 'Пополнить',
        'help' => '#',
    ];

    return [
        'items' => $items
    ];
}


/**
 * Получить информацию для статистики на главной
 * @param array $params массив параметров
 * @param int $params[type] тип. 1: Заказали 2: Забрали
 */

public function getStaInfo(array $params = []): array
{

    $dbh = $this->rateDB->connection;

    $type = @$params['type'];
    if ( !$type ) $type = 1;
    if ( !in_array($type, array(1,2)) ) $type = 1;


    $client_id = @Auth::user()['task1'];
    if ( @$client_id ) $client_id = str_replace('#', '', $client_id);

    $last = $current = [];


    $items = [];

    $point_1 = date("Y-m-d 00:00:00", strtotime('saturday - 2 week'));
    $point_2 = date("Y-m-d 00:00:00", strtotime('saturday - 1 week'));
    $point_3 = date("Y-m-d 00:00:00", strtotime('+1 day'));

    $period_1 = new DatePeriod(
        new DateTime($point_1),
        new DateInterval('P1D'),
        new DateTime($point_2)
    );

    $dates_1 = array();
    foreach ($period_1 as $key => $value) {
        $dates_1[] = $value->format('Y-m-d');
    }

    $period_2 = new DatePeriod(
        new DateTime($point_2),
        new DateInterval('P1D'),
        new DateTime($point_3)
    );

    $dates_2 = array();
    foreach ($period_2 as $key => $value) {
        $dates_2[] = $value->format('Y-m-d');
    }

    if ( $type == 1 ) {
        // Заказали
        // стастика= это все то что с статусом
        // Оплачен, Получить, Получен, Опубликовать, Модерация, Опубликован, Удалён, за неделю
        // по датам покупки, в недельному разрезе (date buy)
        $sql = "
                SELECT DATE(cl.date_buy) `date`, COUNT(*) cnt FROM client cl
                WHERE cl.task1 = ?
                AND cl.date_buy IS NOT NULL
                AND cl.`status` IN ('2', '3', '4','5', '7', '6', '8')
                GROUP BY date          
                HAVING `date` >= ? AND `date` < ?
            ";
    } else if ( $type == 2 ) {
        // Забрали
        // это все то что с статусом Получен, Опубликовать, Модерация, Опубликован, Удалён, за неделю
        // в недельном разрезе по date get
        $sql = "
                SELECT DATE(cl.date_get) `date`, COUNT(*) cnt FROM client cl
                WHERE 1cl.task1 = ?
                AND cl.date_get IS NOT NULL
                AND cl.`status` IN ('3', '4','5', '7', '6', '8')
                GROUP BY date          
                HAVING `date` >= ? AND `date` < ?
            ";
    }
    $stmt = $dbh->prepare( $sql );
    $_rows = [];
    if ( $stmt->execute( [ $client_id, $point_1, $point_2 ] ) ) {
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $_rows[ $row['date'] ] = $row['cnt'];
        }
    }

    foreach ($dates_1 as $d) {
        if ( !@$_rows[ $d ] ) $last[] = 0;
        else $last[] = $_rows[ $d ];
    }

    $_rows = [];
    if ( $stmt->execute( [ $client_id, $point_2, $point_3 ] ) ) {
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $_rows[ $row['date'] ] = $row['cnt'];
        }
    }
    foreach ($dates_2 as $d) {
        if ( !@$_rows[ $d ] ) $current[] = 0;
        else $current[] = $_rows[ $d ];
    }

    // затычак для тестов
    // $_last = $_current = [];
    // for($i=0;$i<count($last);$i++){$_last[]=rand(5,20);}
    // for($i=0;$i<count($current);$i++){$_current[]=rand(5,20);}
    // $last = $_last;
    // $current = $_current;
    //

    return [
        'last' => $last,
        'current' => $current,
        'count' => array_sum($last) + array_sum($current),
        'sum' => 0,
        'update_date' => date('d.m.Y'),
        'update_time' => date('H:i'),
    ];
}
}