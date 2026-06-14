<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Entity;

use App\Domain\Chantier\Exception\TacheInvalideException;
use Symfony\Component\Uid\Uuid;

/**
 * Tâche = élément de checklist d'exécution d'un lot. Entité « légère » (cf ADR 0024) :
 * pas de couche Application dédiée ni de VO, mais immuable et dans le domaine comme
 * le reste du BC Chantier (séparation imposée par Deptrac).
 */
final readonly class Tache
{
    public function __construct(
        public Uuid $id,
        public Uuid $lotId,
        public string $libelle,
        public bool $faite,
        public ?\DateTimeImmutable $faiteLe,
        public int $ordre,
    ) {
    }

    public static function creer(
        Uuid $lotId,
        string $libelle,
        int $ordre = 0,
        ?Uuid $id = null,
    ): self {
        return new self(
            id: $id ?? Uuid::v7(),
            lotId: $lotId,
            libelle: self::libelleValide($libelle),
            faite: false,
            faiteLe: null,
            ordre: $ordre,
        );
    }

    public function cocher(?\DateTimeImmutable $maintenant = null): self
    {
        return new self(
            id: $this->id,
            lotId: $this->lotId,
            libelle: $this->libelle,
            faite: true,
            faiteLe: $maintenant ?? new \DateTimeImmutable(),
            ordre: $this->ordre,
        );
    }

    public function decocher(): self
    {
        return new self(
            id: $this->id,
            lotId: $this->lotId,
            libelle: $this->libelle,
            faite: false,
            faiteLe: null,
            ordre: $this->ordre,
        );
    }

    public function renommer(string $libelle): self
    {
        return new self(
            id: $this->id,
            lotId: $this->lotId,
            libelle: self::libelleValide($libelle),
            faite: $this->faite,
            faiteLe: $this->faiteLe,
            ordre: $this->ordre,
        );
    }

    private static function libelleValide(string $libelle): string
    {
        $libelle = trim($libelle);

        if ($libelle === '') {
            throw TacheInvalideException::libelleVide();
        }

        return $libelle;
    }
}
