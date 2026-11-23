<?php

namespace App\Exceptions;

use RuntimeException;

/** Penolakan yang pesannya aman & ramah untuk ditampilkan ke operator. */
class HierarkiSampahException extends RuntimeException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
