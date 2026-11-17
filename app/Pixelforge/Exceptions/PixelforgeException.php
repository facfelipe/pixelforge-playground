<?php

declare(strict_types=1);

namespace App\Pixelforge\Exceptions;

use Exception;

final class PixelforgeException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 400,
    ) {
        parent::__construct($message, $statusCode);
    }
}
