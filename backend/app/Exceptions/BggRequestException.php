<?php

namespace App\Exceptions;

use RuntimeException;

class BggRequestException extends RuntimeException
{
    private function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function requestFailed(): self
    {
        return new self('Die Anfrage an BoardGameGeek ist fehlgeschlagen.', 502);
    }

    public static function notFound(int $bggId): self
    {
        return new self("Auf BoardGameGeek wurde kein Spiel mit der ID {$bggId} gefunden.", 404);
    }
}
