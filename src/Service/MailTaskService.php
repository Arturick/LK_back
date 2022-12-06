<?php

namespace App\Service;

use App\Support\Connection;

class MailTaskService
{

    private Connection $db;

    public function __construct()
    {

        $this->db = new Connection(config('connection.mailing'));

    }

    public function get(): array
    {

        $sql = "SELECT t.*, s.key AS status_key, s.name AS status_name FROM task t JOIN status s ON t.status_id = s.id";
        return $this->db->select($sql);

    }

    public function create(array $task)
    {

        $required = ['date_from', 'date_to', 'delayed_to'];

        foreach ($required as $r) {
            if (empty($task[$r])) return null;
        }

        $name = $this->getTaskName($task);
        $deadline = empty($task['deadline']) ? null : $task['deadline'];

        $sql = "INSERT INTO task(name, delayed_to, date_from, date_to, deadline) VALUES (?,?,?,?,?)";
        return $this->db->insert($sql, [$name, $task['delayed_to'], $task['date_from'], $task['date_to'], $deadline]);

    }

    private function getTaskName($task): string
    {

        $months = [
            1 => "января", "февраля", "марта",
            "апреля", "мая", "июня",
            "июля", "августа", "сентября",
            "октября", "ноября", "декабря",
        ];

        $monthNum = date('n', strtotime($task['date_from']));
        $month = $months[$monthNum];

        $interval = date('d.m', strtotime($task['date_from'])) . "-" . date('d.m.Y', strtotime($task['date_to']));

        $name = "Клиенты $month с $interval,";
        if (empty($task['deadline']))
            $name .= ' без указания «цены до…»,';
        $name .= " в 2 потока, клиентам без счетов.";

        return $name;

    }

}