<?php

namespace App\Exceptions;

use Exception;

class AppException extends Exception
{
    /**
     * @var int
     */
    protected $code = 500;

    public function __construct($message = "", $code = 0, $previous = null)
    {
        if ($code != 0) {
            $this->code = $code;
        }

        parent::__construct($message, $this->code, $previous);
    }
}
