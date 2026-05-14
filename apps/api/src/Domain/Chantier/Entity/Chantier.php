<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Entity;

use App\Domain\Chantier\Enum\StatutChantier;
use App\Domain\Chantier\Exception\TransitionStatutInvalideException;
use App\Shared\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use Symfony\Component\Uid\Uuid;

final readonly class Chantier
{
    public function __construct(
        public Uuid $id,
        public Uuid $proprietaireId,
        public Adresse $adresse,
        public ?Surface $surface,
        public StatutChantier $statut,
        public \DateTimeImmutable $creeLe,
        public \DateTimeImmutable $modifieLe,
    ) {
    }

    public static function creer(
        Uuid $proprietaireId,
        Adresse $adresse,
        ?Surface $surface = null,
        ?\DateTimeImmutable $maintenant = null,
    ): self {
        $maintenant ??= new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
            proprietaireId: $proprietaireId,
            adresse: $adresse,
            surface: $surface,
            statut: StatutChantier::EN_PREPARATION,
            creeLe: $maintenant,
            modifieLe: $maintenant,
        );
    }

    public function passerEnCours(?\DateTimeImmutable $maintenant = null): self
    {
        return $this->changerStatut(StatutChantier::EN_COURS, $maintenant);
    }

    public function terminer(?\DateTimeImmutable $maintenant = null): self
    {
        return $this->changerStatut(StatutChantier::TERMINE, $maintenant);
    }

    public function archiver(?\DateTimeImmutable $maintenant = null): self
    {
        return $this->changerStatut(StatutChantier::ARCHIVE, $maintenant);
    }

    public function modifierAdresse(Adresse $nouvelleAdresse, ?\DateTimeImmutable $maintenant = null): self
    {
        $maintenant ??= new \DateTimeImmutable();

        return new self(
            id: $this->id,
            proprietaireId: $this->proprietaireId,
            adresse: $nouvelleAdresse,
            surface: $this->surface,
            statut: $this->statut,
            creeLe: $this->creeLe,
            modifieLe: $maintenant,
        );
    }

    public function renseignerSurface(Surface $surface, ?\DateTimeImmutable $maintenant = null): self
    {
        $maintenant ??= new \DateTimeImmutable();

        return new self(
            id: $this->id,
            proprietaireId: $this->proprietaireId,
            adresse: $this->adresse,
            surface: $surface,
            statut: $this->statut,
            creeLe: $this->creeLe,
            modifieLe: $maintenant,
        );
    }

    private function changerStatut(StatutChantier $cible, ?\DateTimeImmutable $maintenant): self
    {
        if ($this->statut === $cible) {
            throw TransitionStatutInvalideException::dejaDansCeStatut($cible);
        }

        if ($this->statut->estTerminal()) {
            throw TransitionStatutInvalideException::depuisStatutTerminal($this->statut, $cible);
        }

        $maintenant ??= new \DateTimeImmutable();

        return new self(
            id: $this->id,
            proprietaireId: $this->proprietaireId,
            adresse: $this->adresse,
            surface: $this->surface,
            statut: $cible,
            creeLe: $this->creeLe,
            modifieLe: $maintenant,
        );
    }
}
