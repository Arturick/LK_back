<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;
use \DatePeriod;
use \DateTime;
use \DateInterval;


class SmsService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService,
        private AuthService $authService,
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }

    /**
     *
     */
    public function action(array $params = []): array
    {

        $dbh = $this->rateDB->connection;

        $output = [];

        if ( $params['action'] == 'send' ) {
            $output = $this->send($params);
        }
        if ( $params['action'] == 'check' ) {
            $output = $this->check($params);
        }

        return $output;
    }

    private function preparePhone($phone) {
        $phone = preg_replace('#[^\d]#', '', $phone);
        return $phone;
    }


    private function send(array $params = []): array
    {
        $dbh = $this->rateDB->connection;


        $phone = @$params['phone'];
        $phone = $this->preparePhone($phone);

        $output = [];

        $output['success'] = false;

        $email = "work@alex-makarov.ru";
        $api_key = "V53E1XMC7lnwKXo6IE3i0CnOeZl4";
        $password = "h6UUVF8@Xnp2a99";


        $code = rand(10000, 99999);
        $text = 'RATE-THIS code: '.$code;

        // $href = "https://{$email}:{$api_key}@gate.smsaero.ru/v2/sms/send?number={$phone}&text={$text}&sign=SMS Aero";
        // $href = "https://smsc.ru/sys/send.php?login={$email}&psw={$password}&phones={$phone}&mes={$text}&sender=SMSC.RU";

        // $json = file_get_contents($href);
        //

        $out = $this->sendBySmsFeedback("api.smsfeedback.ru", 80, "rate-this", "KoBe6263",
              $phone, $text, "RATE-THIS");

        $out = explode(";", $out);
        if ( $out[0] == 'accepted' ) {
            $json = '{"success" : true}';
        }

        // $code = '12345';
        // $json = '{"success" : true}';

        if ( $_data = json_decode($json, true) ) {
            if ( @$_data['success'] ) {
                $sql = 'INSERT INTO code_access (`phone`, `code`, `created_at`) VALUES (?, ? , NOW())';
                $stmt = $dbh->prepare( $sql );
                if ( $stmt->execute( [ $phone, $code ] ) ) {
                    if ( $dbh->lastInsertId() ) {
                        $output['success'] = true;
                    }
                }
            }
        }

        return $output;
    }

    private function check(array $params = []): array
    {

        $dbh = $this->rateDB->connection;

        $output = [];
        $output['success'] = false;

        $code = @$params['code'];
        $phone = @$params['phone'];
        $phone = $this->preparePhone($phone);

        $sql = '
            SELECT * FROM code_access 
            WHERE phone = ?
            AND code = ?
            AND status = 0
        ';
        $stmt = $dbh->prepare( $sql );
        if ( $stmt->execute( [ $phone, $code ] ) ) {

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ( $row ) {

                $sql = '
                    UPDATE code_access ac
                    SET ac.`status` = 1
                    WHERE ac.id = ?
                ';
                $stmt = $dbh->prepare( $sql );
                if ( $stmt->execute( [ $row['id'] ] ) ) {
                    if ( $stmt->rowCount() > 0 ) {
                        $output['success'] = true;
                        $access_token = $this->authService->authByPhone( $phone );
                        if ( $access_token ) {
                            $output['data']['access_token'] = $access_token;
                        }

                    }
                }

            }
        }

        return $output;
    }


    /*
    * функция передачи сообщения
    */

    private function sendBySmsFeedback($host, $port, $login, $password, $phone, $text, $sender = false, $wapurl = false )
    {
        $fp = fsockopen($host, $port, $errno, $errstr);
        if (!$fp) {
            return "errno: $errno \nerrstr: $errstr\n";
        }
        fwrite($fp, "GET /messages/v2/send/" .
            "?phone=" . rawurlencode($phone) .
            "&text=" . rawurlencode($text) .
            ($sender ? "&sender=" . rawurlencode($sender) : "") .
            ($wapurl ? "&wapurl=" . rawurlencode($wapurl) : "") .
            "  HTTP/1.0\n");
        fwrite($fp, "Host: " . $host . "\r\n");
        if ($login != "") {
            fwrite($fp, "Authorization: Basic " .
                base64_encode($login. ":" . $password) . "\n");
        }
        fwrite($fp, "\n");
        $response = "";
        while(!feof($fp)) {
            $response .= fread($fp, 1);
        }
        fclose($fp);
        list($other, $responseBody) = explode("\r\n\r\n", $response, 2);
        return $responseBody;
    }

}
