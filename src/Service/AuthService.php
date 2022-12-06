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

class AuthService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }
    public function save(null|array $params): array
    {

        $dbh = $this->rateDB->connection;
        $output = [];
        $id = @$params['_user']['id'];
        $user = [];
        $u_name = @$params['u_name'];
        $u_surname = @$params['u_surname'];
        $bank = @$params['bank'];
        $wb_api_key = @$params['wb_api_key'];
        $telegram = @$params['telegram'];
        $inn = @$params['inn'];
        $phone = @$params['phone'];
        $suggestions = @$params['suggestions'];
        $number = @$params['number'];
        $ks = @$params['ks'];

        if ( @$suggestions || @$number ) {
            $sql = '
                UPDATE client_data cd
                SET cd.`inn` = ?, cd.`task1` = ?, cd.`suggestions` = ?, cd.`name` = ?
                WHERE cd.id = ?
            ';
            $stmt = $dbh->prepare( $sql );
            if ( $stmt->execute( [ $inn, '#'.$number, json_encode($suggestions), $suggestions[0]['data']['name']['short_with_opf'] , $id ] ) ) {

            }
        } else {
            $sql = '
                UPDATE client_data cd
                SET cd.`u_name` = ?, cd.`u_surname` = ?, cd.`bank` = ?, cd.`ks` = ?, cd.`wb_api_key` = ?, cd.`telegram` = ?
                WHERE cd.phone = ?
            ';
            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [ $u_name, $u_surname, $bank, $ks, $wb_api_key, $telegram, $phone]);
        }


        $sql = '
            SELECT * FROM client_data cd
            WHERE cd.phone = ?
        ';
        $stmt = $dbh->prepare( $sql );
        if ( $stmt->execute( [ $phone] ) ) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $user['name'] = @$row['name'];
            if ( !$user['name']) {
                $user['name'] = $phone;
            }
            if ( !$user['name'] ) {
                $user['name'] = 'User:'.$phone;
            }
            $user['phone'] = $phone;
            $user['inn'] = @$row['inn'];
            $user['wb_api_key'] = @$row['wb_api_key'];
            $user['telegram'] = @$row['telegram'];

            $user['u_surname'] = @$row['u_surname'];
            $user['u_name'] = @$row['u_name'];
            $user['bank'] = @$row['bank'];
            $user['ks'] = @$row['ks'];

            $user['role'] = (int) @$row['role'];

            $output['data']['user'] = $user;
        }


        return $output;
    }

    public function getUserByJwt(null|array $params): array
    {
        $salt = config('app.jwt_salt');
        $dbh = $this->rateDB->connection;

        $output = [];

        $jwt = str_replace('Bearer ', '', @$_SERVER['HTTP_AUTHORIZATION']);
        $jwt = str_replace('Bearer', '', $jwt);

        $decoded_array = [];
        if ( $jwt ) {
            $decoded = JWT::decode($jwt, new Key($salt, 'HS256'));
            $decoded_array = (array) $decoded;
        }


        $id = @$decoded_array['id'];

        $user = [];

        $sql = '
            SELECT * FROM client_data cd
            WHERE cd.id = ?
        ';
        $stmt = $dbh->prepare( $sql );
        if ( $stmt->execute( [ $id ] ) ) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $user['name'] = @$row['name'];
            if ( !$user['name'] && @$row['phone'] ) {
                $user['name'] = @$row['phone'];
            }
            if ( !$user['name'] ) {
                $user['name'] = 'User:'.$row['id'];
            }

            $user['phone'] = @$row['phone'];
            $user['inn'] = @$row['inn'];
            $user['wb_api_key'] = @$row['wb_api_key'];
            $user['telegram'] = @$row['telegram'];

            $user['u_surname'] = @$row['u_surname'];
            $user['u_name'] = @$row['u_name'];
            $user['bank'] = @$row['bank'];
            $user['ks'] = @$row['ks'];

            $user['role'] = (int) @$row['role'];

            $output['data']['user'] = $user;
        }


        return $output;
    }



    /**
     *
     */
    public function authByPhone(string $phone ): null|string
    {

        $salt = config('app.jwt_salt');

        $dbh = $this->rateDB->connection;

        $output = [];

        $sql = '
            SELECT `id` FROM client_data 
            WHERE phone = ?
        ';

        $payload = [];

        $stmt = $dbh->prepare( $sql );
        if ( $stmt->execute( [ $phone ] ) ) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ( !$row ) {

                $sql = 'INSERT INTO client_data (`task1`, `phone`, `role`, `created_at`) VALUES ( ?, ?, 4, NOW() )';
                $stmt = $dbh->prepare( $sql );
                $task1Rand = rand();
                if ( $stmt->execute( [ $task1Rand, $phone ] ) ) {

                    $_id = $dbh->lastInsertId();
                    if ( $_id ) {
                        $row = [];
                        $row['id'] = $_id;
                    }
                }
            }

            if ( $row ) {
                $payload = $row;
            }
        }

        $jwt = '';
        if ( $payload ) {
            $jwt = JWT::encode($payload, $salt, 'HS256');
            return $jwt;
        }

        return null;
    }


    public function getUserById( string $id ): null|array
    {

        $dbh = $this->rateDB->connection;

        $sql = '
            SELECT * FROM client_data cd
            WHERE cd.id = ?
        ';
        $stmt = $dbh->prepare( $sql );
        if ( $stmt->execute( [ $id ] ) ) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row;
        }

        return null;
    }


    public function getParentUsers(null|array $params): array
    {

        $dbh = $this->rateDB->connection;

        $output = [];

        $id = @Auth::user()['id'];

        $sql = '
            SELECT * FROM client_data d
            WHERE d.parent = ?
        ';
        $stmt = $dbh->prepare( $sql );


        $items = [];

        if ( $stmt->execute( [ $id ] ) ) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {


                $fio = [];
                $fio[] = $row['u_name'];
                $fio[] = $row['u_surname'];

                $items[] = [
                    'id' => @$row['id'],
                    'fio' => implode(" ", $fio),
                    'phone' => @$row['phone'],
                    'telegram' => @$row['telegram'],
                    'role' => @$row['role'],
                    // 'action' => '',
                ];
            }
        }

        $output['headers'] = [
            ['text' => 'ФИО', 'value' => 'fio'],
            ['text' => 'Номер', 'value' => 'phone'],
            ['text' => 'Телеграмм ID', 'value' => 'telegram'],
            ['text' => 'Право доступа', 'value' => 'role'],
            ['text' => '', 'value' => 'action'],

        ];
        $output['items'] = $items;

        return $output;

    }
    public function saveParentUsers(null|array $params): array
    {
        $output = [];
        $dbh = $this->rateDB->connection;

        $parent = @Auth::user()['id'];
        $taskParent = @Auth::user()['task1'];

        $output['success'] = false;

        $item = @$params['item'];

        $_id = @$item['id'];
        $_role = @$item['role'];
        if ( $_id ) {
            $sql = '
                UPDATE client_data d
                SET d.role = ?
                WHERE d.id = ?
            ';
            $stmt = $dbh->prepare( $sql );
            if ( $stmt->execute( [ $_role, $_id] ) ) {
                if ( $stmt->rowCount() ) {
                    $output['success'] = true;
                }
            }
        } else {
            $sql = 'SELECT * FROM client_data WHERE phone = ?';
            $stmt = $dbh->prepare( $sql );
            $stmt->execute( [$this->preparePhone($item['phone'])]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if(count($items) > 0){
                $sql = 'UPDATE client_data d
                SET task1 = ?, parent = ?, u_name = ?, u_surname = ?, phone = ?, role = ? WHERE phone = ?';
                $stmt = $dbh->prepare( $sql );
                if ( $stmt->execute( [
                    $taskParent,
                    $parent,
                    $item['name'],
                    $item['surname'],
                    $this->preparePhone($item['phone']),
                    $item['role'],
                    $this->preparePhone($item['phone']),
                ] ) ) {
                    if ( $dbh->lastInsertId() ) {
                        $output['success'] = true;
                    }
                }

            } else {
                $sql = 'INSERT INTO client_data (`task1`,`parent`,`u_name`, `u_surname`, `phone`, `role`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, NOW())';
                $stmt = $dbh->prepare( $sql );
                if ( $stmt->execute( [
                    $taskParent,
                    $parent,
                    $item['name'],
                    $item['surname'],
                    $this->preparePhone($item['phone']),
                    $item['role'],
                ] ) ) {
                    if ( $dbh->lastInsertId() ) {
                        $output['success'] = true;
                    }
                }
            }


        }

        return [$items];
    }
    public function delParentUsers(null|array $params): array
    {
        $output = [];
        $dbh = $this->rateDB->connection;

        $output['success'] = false;

        $item = @$params['item'];
        $_id = @$item['id'];

        if ( $_id ) {
            $sql = '
                DELETE d FROM client_data d
                WHERE d.id = ?
            ';
            $stmt = $dbh->prepare( $sql );
            if ( $stmt->execute( [ $_id] ) ) {
                if ( $stmt->rowCount() ) {
                    $output['success'] = true;
                }
            }

        }
        return $output;
    }

    private function preparePhone($phone) {
        $phone = preg_replace('#[^\d]#', '', $phone);
        return $phone;
    }
}
