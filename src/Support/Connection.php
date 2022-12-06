<?php

namespace App\Support;

class Connection
{
    public \PDO $connection;

    public function __construct($config)
    {
        try {
            switch ($config['driver']) {
                case 'mysql':
                    $dsn = "mysql:dbname={$config['db']};host={$config['host']}:{$config['port']};charset=utf8";
                    break;
                case 'pgsql':
                    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['db']};";
                    break;
                case 'dblib':
                    $dsn = "dblib:dbname={$config['db']};host={$config['host']}:{$config['port']};charset=UTF-8";
                    break;
            }

            $this->connection = new \PDO(
                $dsn,
                $config['user'],
                $config['password'],
                [
                    \PDO::ATTR_TIMEOUT => 30,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function select(string $sql, array $params = []): array
    {
        $query = $this->connection->prepare($sql);
        $query->execute($params);

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insert(string $sql, array $params): int
    {
        $query = $this->connection->prepare($sql);
        $query->execute($params);

        return $this->connection->lastInsertId();
    }

    public function update(string $sql, array $params = []): int
    {
        $query = $this->connection->prepare($sql);
        $query->execute($params);

        return true;
    }
}
