<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Exception;

final class TacheInvalideException extends ChantierException
{
    public static function libelleVide(): self
    {
        return new self('Le libellé d\'une tâche ne peut pas être vide.');
    }
}
