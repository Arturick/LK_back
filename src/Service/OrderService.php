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


class OrderService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }

    /**
     * Сохранение в промежуточную таблицу
     */
    public function save( array $params = [] ): array
    {
        $dbh = $this->rateDB->connection;

        $output = [];

        $error = 0;

        $group = time();

        // $task1 = 144;
        $task1 = @Auth::user()['task1'];
        $phone = @Auth::user()['phone'];
        if ( @$task1 ) $task1 = str_replace('#', '', $task1);

        $shop = 'wb';
        $types = ['выкуп', 'отзыв'];

        $items = @$params['items'];
        $model = @$params['model'];
        $type = @$params['type'];
        $image = @$params['image'];

        if ( $type == 'bid' ) {
            $status = '1';
        }
        if ( $type == 'draft' ) {
            $status = 'Черновик';
        }


        // {"value":"m1", "name":"Под ключ"},
        // {"value":"m2", "name":"Самостоятельно"}
        if ( $model == 'm1' ) $model = 'RATE-THIS';
        if ( $model == 'm2' ) $model = null;

        $_items = [];
        foreach ($items as $item) {
            $ar = $item;

            $_item = [
                'group' => $group,
                'brand' => $ar['brand'],
                'grafik' => $ar['date'],
                'date_buy' => $ar['date'],
                'mp' => $shop,
                'type' => null,
                'article' => $ar['art'],
                'size' => $ar['size'],
                'price' => $ar['price'],
                'search_key' => $ar['query'],
                'barcode' => $ar['barcode'],
                'img_wb' => $ar['image'],
                'sex' => (@$ar['gender']) ? $ar['gender']: null,
                'kto_zabirat' => $model,
                'brand' => $ar['brand'],
                'naming' => @$ar['naming'],
                'date_add' => @$ar['date_add'],
                'task1' => $task1,

            ];

            // Делим по выкупам и отзывам магия Хогвардса
            if ( @$ar['rcount'] && $ar['rcount'] > 0 ) {
                do {
                    $__item = $_item;
                    $__item['type'] = $types[1]; //  отзыв
                    $__item['grafik'] = $ar['date'];
                    $_items[] = $__item;
                    --$ar['count'];
                    --$ar['rcount'];
                } while ($ar['rcount'] > 0);
            }

            if ( @$ar['count'] && $ar['count'] > 0 ) {
                do {
                    $__item = $_item;
                    $__item['type'] = $types[0]; //  выкуп
                    $_items[] = $__item;
                    --$ar['count'];
                } while ($ar['count'] > 0);
            }
            //

        }
        $items = $_items;
        unset($_items);

        // print_r($items);

        $fields = array_keys($items[0]);
        $fields = array_map(function($v){
            return '`'.$v.'`';
        }, $fields);

        $sql = '
            INSERT INTO `client` ('.implode(",", $fields).')
            VALUES 
        ';

        $values = [];
        $inputs = [];
        foreach ($items as $item) {
            $values[] = '('.implode(",", array_fill(0, count($items[0]), '?')).')';
            $inputs = array_merge($inputs, array_values( $item ));
        }
        $sql .= "\n ".implode(",\n", $values);

        $stmt = $dbh->prepare( $sql );
        if ( $stmt->execute( $inputs ) ) {
            $output['msg'] = 'Заявка создана';
            $href = "https://api.telegram.org/bot5432493400:AAEJRVLf4QO7q0w2EMDcE75NY39faYhu5Oc/sendMessage?chat_id=366950309&text=task1: ".$task1." с телефоном: ".$phone." создал заявку";
            file_get_contents($href);
        } else {
            ++$error;
        }

        if ( $error > 0 ) {
            $output['error'] = true;
            if ( !@$output['msg'] ) {
                $output['msg'] = 'Чтото пошло не так';
            }

        }


        return $output;
    }

}
