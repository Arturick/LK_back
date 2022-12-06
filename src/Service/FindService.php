<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;
use \DatePeriod;
use \DateTime;
use \DateInterval;
use \GuzzleHttp\Client;

use App\Support\Auth;

class FindService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService,

    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }

    /**
     * Распределение калочества по датам
     */
    public function splitByDate( array $params = [] ): array
    {

        $dateRange = @$params['date'];
        $items = @$params['items'];

        usort($dateRange, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        $startDate = @$dateRange['startDate'];
        $endDate = @$dateRange['endDate'];

        if ( !$startDate ) {
            $startDate = @$dateRange[0];
        }
        if ( !$endDate ) {
            $endDate = @$dateRange[1];
        }

        $period = new DatePeriod(
             new DateTime($startDate),
             new DateInterval('P1D'),
             new DateTime(date('Y-m-d', strtotime($endDate. ' +1 day')))
        );

        $dates = array();
        foreach ($period as $key => $value) {
            $dates[] = $value->format('Y-m-d');
        }

        $days = count($dates);

        $prepareInfo = [];
        $results = [];
        $arts = [];
        foreach ($items as $key => $item) {
            $art = $item['art'];

            $arts[] = $art;

            $_dates = $dates;
            if ( $days <= $item['count'] ) {
                $_dates = array_reverse($dates);
            }

            $count = $item['count'];

            do {
                foreach ($_dates as $date) {
                    if ( !isset($prepareInfo[ $key.'_'.$art.'_'.$date ]) ) {
                        $prepareInfo[ $key.'_'.$art.'_'.$date ] = [ 'count' => 0, 'rcount' => 0 ];
                    }
                    ++$prepareInfo[ $key.'_'.$art.'_'.$date ]['count'];
                    --$count;
                    if ( $count <= 0 ) break;
                }
            } while ($count > 0);

            $rcount = $item['rcount'];
            do {
                foreach ($_dates as $date) {
                    if ( !isset($prepareInfo[ $key.'_'.$art.'_'.$date ]) ) {
                        $prepareInfo[ $art.'_'.$date ] = [ 'count' => 0, 'rcount' => 0 ];
                    }
                    ++$prepareInfo[ $key.'_'.$art.'_'.$date ]['rcount'];
                    --$rcount;
                    if ( $rcount <= 0 ) break;
                }
            } while ($rcount > 0);

        }



        foreach ($items as $key =>  $item) {
            $art = $item['art'];
            foreach ($dates as $date) {
                if ( isset( $prepareInfo[ $key.'_'.$art.'_'.$date ] ) ) {
                    $_info = $prepareInfo[ $key.'_'.$art.'_'.$date ];
                    $item['count'] = $_info['count'];
                    $item['rcount'] = $_info['rcount'];

                    $item['date'] = $date;
                    $results[] = $item;
                }
            }
        }

        $arts = array_unique($arts);
        $arts = array_values($arts);


        $headers = [
            ["text" => "Фото", "name" => "Фото" , "key" => 'image', "value" => 'image'],
            ["text" => "Бренд", "name" => "Бренд" , "key" => 'brand', "value" => 'brand'],
            ["text" => "Артикул", "name" => "Артикул" , "key" => 'art', "value" => 'art'],
            ["text" => "Цена WB", "name" => "Цена WB" , "key" => 'price', "value" => 'price'],
            ["text" => "Размер", "name" => "Размер" , "key" => 'size', "value" => 'size'],
            ["text" => "Баркод", "name" => "Баркод" , "key" => 'barcode', "value" => 'barcode'],
            ["text" => "Кол-во выкупов", "Кол-во выкупов" => "Выкупов" , "key" => 'count', "value" => 'count'],
            ["text" => "Кол-во", "name" => "Кол-во отзывов" , "key" => 'rcount', "value" => 'rcount'],
            ["text" => "Запрос", "name" => "Запрос" , "key" => 'query', "value" => 'query'],
            ["text" => "Позиция", "name" => "Позиция" , "key" => 'position', "value" => 'position'],
            ["text" => "Пол", "name" => "Пол" , "key" => 'gender', "value" => 'gender'],
            ["text" => "Дата", "name" => "Дата" , "key" => 'date', "value" => 'date'],
            ["text" => "", "name" => "", "key" => 'del', "value" => 'del'],
        ];


        return [
            'days' => $days,
            'items' => $results,
            'headers' => $headers,
            'arts' => $arts
        ];
    }

    /**
     * Получить позицию
     */
    public function checkAllQuery( array $params = [] ): array
    {

        $output = [];
        $results = [];
        $errors = [];

        $items = @$params['items'];
        foreach ($items as $index => $item) {

            $_errors = [];

            $res = $item;
            // if ( $item['position'] <= 0 ) {
                $res = $this->checkQuery( $item );
            // }
            $res['index'] = $index;
            $res['class'] = '';

            if ( @$item['sizes'] && count(@$item['sizes']) > 1 ) {
                if ( !@$item['size'] ) {
                    $_errors[] = 'Не по всем продуктам выбран размер';
                } else {
                    $_sizes_value = [];
                    foreach ($item['sizes'] as $_row) {
                        $_sizes_value[] = $_row['value'];
                    }
                    if ( !in_array(@$item['size'], $_sizes_value) ) {
                        $_errors[] = 'Выбранный размер не найден';
                    }
                }
            }


            if ( $res['position'] == 0 ) {
                $_errors[] = 'Не по всем продуктам удалось определить позицию';
            }

            if ( !@$item['barcode'] ) {
                $_errors[] = 'Не по всем продуктам указан Баркод';

            }

            if ( count($_errors) > 0 ) {
                $errors = array_merge($errors, $_errors);
                $res['class'] = 'tr-dunger';
            }

            $results[] = $res;

        }
        unset($item);

        $output = [
            'data' => $results
        ];

        $output['error'] = false;
        if ( count($errors) > 0 ) {
            $output['error'] = true;
            $errors = array_unique($errors);
            $errors = array_values($errors);
            $output['msgs'] = $errors;
        }

        return $output;
    }

    /**
     * Получить позицию
     */





    public function findByArt( array $params = [] ): array
{

    $output = [];
    $output['error'] = false;

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
        ["name" => "", "key" => 'copy', "value" => 'copy', 'sortable' => false],
        ["name" => "", "key" => 'del', "value" => 'del', 'sortable' => false],
    ];

    $items = [];

    $errors = 0;
    $art = @$params['art'];
    $date = @$params['date'];

    if ( $art  && !$date) {
        $href = 'https://wbx-content-v2.wbstatic.net/ru/'.$art.'.json';
        $info = [];

        $json = file_get_contents($href);

        if ( $data = json_decode($json, true) ) {
            if ( @$data['nm_id'] ) {
                $info['art'] = $art; //
                $info['name'] = @$data['imt_name']; // Наименование
                $info['brand'] = @$data['selling']['brand_name']; // Название бренда
                $info['brand_id'] = @$data['data']['brand_id']; //
                $info['price'] = ''; // Цена basicPriceU
                // $info['name'] = @$data['imt_name']; // Размеры если они есть
                $info['photo'] = 'https://images.wbstatic.net/c246x328/new/'.floor($art/10000).'0000/'.$art.'-1.jpg'; // Фото товара
                @$info['size'] = '';

                $href = 'https://card.wb.ru/cards/detail?spp=0&regions=64,83,4,38,80,33,70,82,86,30,69,22,66,31,40,1,48&pricemarginCoeff=1.0&reg=0&appType=1&emp=0&locale=ru&lang=ru&curr=rub&couponsGeo=2,12,7,3,6,21&dest=-1075831,-115135,-1084793,12358353&nm='.$art;
                $json = file_get_contents($href);
                if ( $data = json_decode($json, true) ) {
                    if ( count($data['data']['products']) > 0 ) {
                        $product = @$data['data']['products'][0];
                        if ( @$product['salePriceU'] ) {
                            $info['price'] = floor($product['salePriceU']/100);
                        } else {
                            $info['price'] = floor($product['extended']['basicPriceU']/100);
                        }

                        $info['sizes'] = [];
                        if ( @$product['sizes'] ) {
                            if ( count(@$product['sizes']) > 1 ) {
                                $info['sizes'][] = ['value' => '', 'name' => 'Нет', 'text' => 'Нет'];
                                foreach ($product['sizes'] as $s) {
                                    $info['sizes'][] = ['value' => @$s['origName'], 'name' => @$s['origName'], 'text' => @$s['origName']];
                                }
                            } else if ( count(@$product['sizes']) == 1 ) {
                                @$info['size'] = @$product['sizes'][0]['origName'];
                            }

                        }
                    }
                }
            } else {
                ++$errors;
                $output['msg'] = 'Не нашли по артикулу';
            }
        } else {
            ++$errors;
            $output['msg'] = 'Не нашли по артикулу';
        }

        if ( $errors == 0 ) {
            $dbh = $this->rateDB->connection;
            $dataNow = date('d-m-y');
            $item = [
                "image" => @$info['photo'],
                "brand" => @$info['brand'],
                "art" => @$info['art'],
                "price" => @$info['price'],
                "sizes" => @$info['sizes'],
                "size" => @$info['size'],
                "barcode" => '',
                "count" => 1,
                "rcount" => 0,
                "query" => '',
                "naming" => @$info['name'],
                "position" => -1,
                "gender" => '',
                "copy" => '',
                "date_add" => $dataNow,
                "del" => '',
            ];
            $sql = '
                UPDATE client SET img_wb = ? WHERE article = ?
                ';
            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [@$info['photo'], $art]);

            $items[] = $item;
        }

    }

    if($date){
        $dbh = $this->rateDB->connection;

        $sql = '
                    DELETE FROM client WHERE `article` = ? and date_buy = ?
            ';
        $stmt = $dbh->prepare( $sql );
        $stmt->execute( [$date, $art]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [$items];
    }
    if ( $errors > 0 ) {
        $output['error'] = true;
    }

    $output['headers'] = $headers;
    $output['items'] = $items;

    return $output;
}









    public function checkQuery( array $params = [] ): array
    {

        $maxPage = 65; // Кол-во страниц на которых искать
        $art = @$params['art'];
        $query = @$params['query'];

        $position = 0;
        if ( $query ) {
            $_query = urlencode($query);
            $page = 1;
            $info = [];
            $hrefArt = 'https://wbx-content-v2.wbstatic.net/ru/'.$art.'.json';
            $jsonArt = file_get_contents($hrefArt);
            $dataArt = json_decode($jsonArt, true);
            do {
                $href = 'https://search.wb.ru/exactmatch/ru/common/v4/search?appType=1&couponsGeo=12,3,18,15,21&curr=rub&dest=-1029256,-102269,-2162196,-1257786&emp=0&lang=ru&locale=ru&page='.$page.'&pricemarginCoeff=1.0&query='.$_query.'&reg=0&regions=68,64,83,4,38,80,33,70,82,86,75,30,69,22,66,31,40,1,48,71&resultset=catalog&sort=popular&spp=0&suppressSpellcheck=false';
                $json = file_get_contents($href);

                if ( $data = json_decode($json, true) ) {

                    if ( !@$data['data']['products'] ) break;
                    if ( !is_array($data['data']['products']) ) break;
                    if ( count($data['data']['products']) == 0 ) break;


                    $found = array_filter($data['data']['products'], function($v) use ($art, $dataArt){//['imt_name'];
                      return ($v['id'] == $art || ($v['brand'] == $dataArt['selling']['brand_name'] && $v['name'] == $dataArt['imt_name']));
                    });

                    if ( count($found) > 0 ) {
                        $position = array_keys($found)[0] + 1;
                        $position = $position + ( $page - 1 ) * 100;
                        break;
                    }

                } else {
                    break;
                }

                ++$page;
            } while ($page <= $maxPage);
        }

        return [
            'art' => $art,
            'query' => $query,
            'position' => $position
        ];
    }


    public function bulk( array $params = [] ): array
    {

        $output = [];

        $type = @$params['type'];


        $output['msgs'] = [];
        $output['items'] = [];
        $output['headers'] = [];

        if ( $type == 1 ) {
            $arts = @$params['arts'];

            $arts = preg_replace("#[\s,]#", ' ', $arts);
            $arts = preg_replace("#\s+#", ' ', $arts);

            $arts = trim($arts);
            $arts = explode(" ", $arts);

            foreach ($arts as $art) {
                $res = $this->findByArt( ['art' => $art] );

                if ( @$res['headers'] && !@$output['headers'] ) {
                    $output['headers'] = $res['headers'];
                }

                if ( @$res['items']  ) {
                    $output['items'] = array_merge($output['items'], @$res['items']);
                }

                if ( @$res['error'] ) {
                    $output['msgs'][] = $res['msg'];
                }
            }
        }

        if ( $type == 2 ) {

            foreach ($params['files'] as $file) {
                $cmd = 'xlsx2csv -d ";" '.$file.' '.$file.'.csv';
                shell_exec($cmd);

                if ( file_exists($file.'.csv') ) {
                    $_rows = file($file.'.csv');
                    $_rows = array_map(function( $v ){
                        return trim($v);
                        $v = trim($v);
                    }, $_rows);

                    $_rows = array_filter($_rows, function($v){
                        return preg_match('#^\d{3}#ui', $v);
                    });
                    $_rows = array_values($_rows);


                    foreach ($_rows as $_row) {
                        $_row = explode(";", $_row);
                        $_row = array_map( function($v){
                            $v = str_replace('.000000', '', $v);
                            return $v;
                        }, $_row);

                        $res = $this->findByArt( ['art' => $_row[0]] );

                        if ( @$res['headers'] && !@$output['headers'] ) {
                            $output['headers'] = $res['headers'];
                        }

                        if ( @$res['items']  ) {

                            $item = $res['items'][0];

                            if ( @$_row[1] ) $item['barcode'] = $_row[1];
                            if ( @$_row[3] ) $item['count'] = $_row[3];
                            if ( @$_row[4] ) $item['rcount'] = $_row[4];

                            if ( @$_row[2] ) {
                                $_exp = explode(',', @$_row[2]);
                                foreach ($_exp as $_q) {
                                    $item['query'] = $_q;
                                    $output['items'] = array_merge($output['items'], [$item]);
                                }
                            } else {
                                $output['items'] = array_merge($output['items'], [$item]);
                            }


                        }

                        if ( @$res['error'] ) {
                            $output['msgs'][] = $res['msg'];
                        }
                    }
                }
            }
        }

        return $output;
    }

    /**
     * Получить по артиклу из wb
     */


    /**
     * Получить из wb по api key
     */
    public function findByApiKey( array $params = [] ): array
    {

        $output = [];
        $output['error'] = false;

        $errors = 0;

        $_params = [];
        $_params['search'] = @$params['search'];
        $_params['skip'] = @$params['skip'];
        $_params['take'] = @$params['take'];

        if ( !(@$_params['skip'] && @$_params['skip'] >= 0) ) {
            $_params['skip'] = 0;
        }
        if ( !(@$_params['take'] && @$_params['take'] >= 0) ) {
            $_params['take'] = 10;
        }
        $query = http_build_query($_params);
        $href = 'https://suppliers-api.wildberries.ru/api/v2/stocks?'.$query;

        $apiKey = @Auth::user()['wb_api_key'];

        if ( !$apiKey ) {
            $output['error'] = true;
            $output['msg'] = 'Проблемы с api key';

            return $output;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $href,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_HTTPHEADER => array(
            "authorization: {$apiKey }",
            "cache-control: no-cache",
            "content-type: application/json",
            "postman-token: 45b8a6f7-f985-fd77-b046-a4f8bc6e9863"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);


        $headers = [
            ['name' => '', "key" => 'check', "value" => 'check', 'sortable' => false],
            ["name" => "Фото", "key" => 'image', "text" => "Фото", "value" => 'image', 'sortable' => false],
            ["name" => "Бренд", "key" => 'brand', "text" => "Бренд", "value" => 'brand', 'sortable' => false],
            ["name" => "Артикул", "key" => 'art', "text" => "Артикул", "value" => 'art', 'sortable' => false],
            ["name" => "Цена WB", "key" => 'price', "text" => "Цена WB", "value" => 'price', 'sortable' => false],
            ["name" => "Размер", "key" => 'size', "text" => "Размер", "value" => 'size', 'sortable' => false],
            ["name" => "Баркод", "key" => 'barcode', "text" => "Баркод", "value" => 'barcode', 'sortable' => false],
            // ["name" => "Выкупов", "key" => 'count', "text" => "Выкупов", "value" => 'count'],
            // ["name" => "Кол-во отзывов", "key" => 'rcount', "text" => "Кол-во отзывов", "value" => 'rcount'],
            // ["name" => "Запрос", "key" => 'query', "text" => "Запрос", "value" => 'query'],
            // ["name" => "Позиция", "key" => 'position', "text" => "Позиция", "value" => 'position'],
            // ["name" => "Пол", "key" => 'gender', "text" => "Пол", "value" => 'gender'],
            // ["name" => "", "key" => 'copy', "value" => 'copy'],
            // ["name" => "", "key" => 'del', "value" => 'del'],
        ];

        $items = [];

        if ($err) {
          echo "cURL Error #:" . $err;
        } else {

            if ( @$response ) {
                if ( $data = json_decode($response, true) ) {
                    if ( $data['total'] > 0 ) {

                        foreach ($data['stocks'] as $item) {

                            $photo = 'https://images.wbstatic.net/c246x328/new/'.floor($item['nmId']/10000).'0000/'.$item['nmId'].'-1.jpg'; // Фото товара

                            $href = 'https://card.wb.ru/cards/detail?spp=0&regions=64,83,4,38,80,33,70,82,86,30,69,22,66,31,40,1,48&pricemarginCoeff=1.0&reg=0&appType=1&emp=0&locale=ru&lang=ru&curr=rub&couponsGeo=2,12,7,3,6,21&dest=-1075831,-115135,-1084793,12358353&nm='.$item['nmId'];
                            $json = file_get_contents($href);
                            if ( $data = json_decode($json, true) ) {
                                if ( count($data['data']['products']) > 0 ) {
                                    $product = @$data['data']['products'][0];

                                    if ( @$product['salePriceU'] ) {
                                        $item['price'] = floor($product['salePriceU']/100);
                                    } else if ( @$product['priceU'] ) {
                                        $item['price'] = floor($product['priceU']/100);
                                    }

                                    $info['sizes'] = [];
                                    if ( @$product['sizes'] ) {
                                        if ( count(@$product['sizes']) > 1 ) {
                                            $info['sizes'][] = ['value' => '', 'name' => 'Нет', 'text' => 'Нет'];
                                            foreach ($product['sizes'] as $s) {
                                                $info['sizes'][] = ['value' => @$s['origName'], 'name' => @$s['origName'], 'text' => @$s['origName']];
                                            }
                                        } else if ( count(@$product['sizes']) == 1 ) {
                                            @$info['size'] = @$product['sizes'][0]['origName'];
                                        }
                                    }
                                }
                            }

                            $item = [
                                "check" => false,
                                "image" => $photo,
                                "brand" => @$item['brand'],
                                "art" => @$item['nmId'],
                                "size" => @$item['size'],
                                "sizes" => @$info['sizes'],
                                "barcode" => @$item['barcode'],
                                "sart" => @$item['article'],
                                "price" => @$item['price'],
                                "count" => 1,
                                "rcount" => @$item["stock"],
                                "query" => '',
                                "position" => -1,
                                "gender" => '',
                                "copy" => '',
                                "del" => '',
                            ];
                            $items[] = $item;
                        }

                    }
                }
            }
        }

        if ( $errors > 0 ) {
            $output['error'] = true;
        }

        $output['headers'] = $headers;
        $output['items'] = $items;

        return $output;
    }


    public function moveUploadedFile($directory, $uploadedFile) {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }
}
