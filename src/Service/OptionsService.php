<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;

use App\Support\Auth;

class OptionsService
{
    private Connection $rateDB;

    public function __construct(
        private ExcelService $excelService,
        private EmailService $emailService
    ) {
        $this->rateDB = new Connection(config('connection.rate'));
    }
    public function list(null|array $params): array
    {

        $dbh = $this->rateDB->connection;
        $output = [];


        $sql = '
            SELECT r.`id` AS `value`, r.`name` AS `text`  FROM client_roles r
        ';

        $stmt = $dbh->prepare( $sql );
        if ( $stmt->execute() ) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $output['opt_roles'] = $rows;
        }

        $role = @Auth::user()['role'];

        if ( $role != 4 ) {
            $filter_roles = [4]; // 
            $output['opt_roles'] = array_filter($output['opt_roles'], function( $v ) use ($filter_roles){
                return !in_array($v['id'], $filter_roles);
            }) ;
            $output['opt_roles'] = array_values($output['opt_roles']); 
        }

        return $output;
    }



}
