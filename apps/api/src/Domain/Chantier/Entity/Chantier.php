<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Entity;

use App\Domain\Chantier\Enum\StatutChantier;
use App\Domain\Chantier\Exception\TransitionStatutInvalideException;
use App\Domain\Chantier\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use Symfony\Component\Uid\Uuid;

final readonly class Chantier
{
    public function __construct(
        public Uuid $id,
        public Adresse $adresse,
        public ?Surface $surface,
        public StatutChantier $statut,
        public \DateTimeImmutable $creeLe,
        public \DateTimeImmutable $modifieLe,
    ) {
    }

    public static function creer(
        Adresse $adresse,
        ?Surface $surface = null,
        ?\DateTimeImmutable $maintenant = null,
    ): self {
        $maintenant ??= new \DateTimeImmutable();

        return new self(
            Uuid::v7(),
            $adresse,
            $surface,
            StatutChantier::EN_PREPARATION,
            $maintenant,
            $maintenant,
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
            $this->id,
            $nouvelleAdresse,
            $this->surface,
            $this->statut,
            $this->creeLe,
            $maintenant,
        );
    }

    public function renseignerSurface(Surface $surface, ?\DateTimeImmutable $maintenant = null): self
    {
        $maintenant ??= new \DateTimeImmutable();

        return new self(
            $this->id,
            $this->adresse,
            $surface,
            $this->statut,
            $this->creeLe,
            $maintenant,
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
            $this->id,
            $this->adresse,
            $this->surface,
            $cible,
            $this->creeLe,
            $maintenant,
        );
    }
}
