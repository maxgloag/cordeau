<?php

declare(strict_types=1);

namespace App\Application\Devis\Validation;

final class DemandeDevisInvalideException extends \InvalidArgumentException
{
    /**
     * @param non-empty-list<ErreurValidation> $erreurs
     */
    public function __construct(
        public readonly array $erreurs,
    ) {
        parent::__construct(\sprintf('Demande de devis invalide : %d erreur(s) de validation.', \count($erreurs)));
    }
}
