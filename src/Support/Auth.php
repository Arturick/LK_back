<?php

namespace App\Support;

use App\Exceptions\AppException;
use App\Support\Connection;
use \PDO;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{

    private Connection $rateDB;
    private static $user = [];

    public static function init( $id )
    {
        self::$user = self::getUserById( $id );
    }

    public static function user()
    {
        return self::$user;
    }    

    public static function getUserById( string $id ): null|array
    {
        $rateDB = new Connection(config('connection.rate'));
        $dbh = $rateDB->connection;

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
}
