<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class UnsupportedFileTypeException extends Exception
{
    public function __construct(string $message = 'Unsupported file type provided', int $code = 415, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
