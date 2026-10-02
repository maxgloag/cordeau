<?php

declare(strict_types=1);

namespace App\Application\Devis\Validation;

/**
 * Accumule les erreurs d'une validation (toutes les erreurs sont rapportées, sans
 * arrêt à la première). Une même erreur (code + ligne) n'est retenue qu'une fois.
 */
final class CollecteurErreurs
{
    /** @var array<string, ErreurValidation> */
    private array $erreurs = [];

    /**
     * @param int<0, max>|null $ligne
     */
    public function ajouter(CodeErreur $code, ?int $ligne = null): void
    {
        $this->erreurs[$code->value.'#'.($ligne ?? '-')] = new ErreurValidation($code, $ligne);
    }

    public function estVide(): bool
    {
        return $this->erreurs === [];
    }

    public function contientUneErreurDeLigne(): bool
    {
        foreach ($this->erreurs as $erreur) {
            if ($erreur->ligne !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws DemandeDevisInvalideException si au moins une erreur a été collectée
     */
    public function leverSiNonVide(): void
    {
        $erreurs = array_values($this->erreurs);
        if ($erreurs !== []) {
            throw new DemandeDevisInvalideException($erreurs);
        }
    }
}
