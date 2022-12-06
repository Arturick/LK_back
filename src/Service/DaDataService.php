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


class DaDataService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }


    public function search(null|array $params): array
    {
        $dbh = $this->rateDB->connection;
        $output = [];

        $inn = @$params['inn'];

        $result = $this->findByInn( $inn );
        $output['suggestions'] = @$result['suggestions'];

        $output['data'] = [];

        foreach ($output['suggestions'] as $item) {
            $output['data'][] = [
                'name' => @$item['data']['name']['short_with_opf'],
                'address' => @$item['data']['address']['value'],
                'ogrn' => @$item['data']['ogrn'],
                'okpo' => @$item['data']['okpo'],
                'inn' => @$item['data']['inn'],
                'kpp' => @$item['data']['kpp'],
                'bank' => @$params['_user']['bank'],
                'ks' => @$params['_user']['ks'], 
            ];
        }


        return $output;
    }

    private static function findByInn( $inn ) {

        $url = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party';

        $headers = array();
        $headers[] = "Content-Type: application/json";
        $headers[] = "Accept: application/json";
        $headers[] = "Authorization: Token " . config('app.dadata_api_key');

        $postfields = [
            'query' =>  $inn,
            "status" => ["ACTIVE"]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $result = json_decode($result, true);

        return $result;
    }


}
