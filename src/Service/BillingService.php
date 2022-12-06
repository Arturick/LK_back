<?php

namespace App\Service;

use App\Exceptions\AppException;
use App\Support\Connection;
use Carbon\Carbon;
use \PDO;
use \DatePeriod;
use \DateTime;
use \DateInterval;


class BillingService
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
     * Выставить счёт
     */
    public function bill(array $params = []): array
    {

        $output = [];
        $output['success'] = false;

        $amount = @$params['amount']; 
        $type_id = @$params['type_id']; 
        $client_data_id = @Auth::user()['id'];

        if ( !preg_match('#^\d+$#', $amount) ) {
            $output['msg'] = 'Amount is not valid';
            return $output;
        }
        if ( $amount <= 0 ) {
            $output['msg'] = 'Amount is not valid';
            return $output;
        }

        if ( $type_id <= 0 ) {
            $output['msg'] = 'Type invoice is not valid';
            return $output;
        }

        $dbh = $this->rateDB->connection;

        $invoice_id = null;

        $sql = '
            INSERT INTO invoices (`date`, `amount`, `client_data_id`, `type_id`, `status_id`, `created_at`) 
            VALUES (NOW(), ?, ?, ?, 1, NOW())
        ';
        $stmt = $dbh->prepare($sql);
        if ( $stmt->execute([$amount, $client_data_id, $type_id]) ) {
            $invoice_id = $dbh->lastInsertId();
        }


        return $output;
    }


}
