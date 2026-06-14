<?php

declare(strict_types=1);

namespace App\Domain\Chantier\Entity;

use App\Domain\Chantier\Enum\ModeFacturation;
use App\Domain\Chantier\Exception\LotInvalideException;
use App\Domain\Chantier\ValueObject\Estimation;
use App\Domain\Chantier\ValueObject\Imprevu;
use App\Domain\Chantier\ValueObject\Reel;
use Symfony\Component\Uid\Uuid;

/**
 * Lot = poste de travail d'un chantier (1-N optionnel, cf ADR 0015). Entité riche
 * immuable : porte le mode de facturation, l'estimation/réel et leurs invariants.
 */
final readonly class Lot
{
    /**
     * @param list<Imprevu> $imprevus
     */
    public function __construct(
        public Uuid $id,
        public Uuid $chantierId,
        public string $nom,
        public ModeFacturation $mode,
        public ?Estimation $estimation,
        public ?Reel $reel,
        public int $ordre,
        public array $imprevus,
    ) {
    }

    public static function creer(
        Uuid $chantierId,
        string $nom,
        ModeFacturation $mode,
        int $ordre = 0,
        ?Uuid $id = null,
    ): self {
        return new self(
            id: $id ?? Uuid::v7(),
            chantierId: $chantierId,
            nom: self::nomValide($nom),
            mode: $mode,
            estimation: null,
            reel: null,
            ordre: $ordre,
            imprevus: [],
        );
    }

    public function renommer(string $nom): self
    {
        return new self(
            id: $this->id,
            chantierId: $this->chantierId,
            nom: self::nomValide($nom),
            mode: $this->mode,
            estimation: $this->estimation,
            reel: $this->reel,
            ordre: $this->ordre,
            imprevus: $this->imprevus,
        );
    }

    public function definirEstimation(Estimation $estimation): self
    {
        return new self(
            id: $this->id,
            chantierId: $this->chantierId,
            nom: $this->nom,
            mode: $this->mode,
            estimation: $estimation,
            reel: $this->reel,
            ordre: $this->ordre,
            imprevus: $this->imprevus,
        );
    }

    public function enregistrerReel(Reel $reel): self
    {
        return new self(
            id: $this->id,
            chantierId: $this->chantierId,
            nom: $this->nom,
            mode: $this->mode,
            estimation: $this->estimation,
            reel: $reel,
            ordre: $this->ordre,
            imprevus: $this->imprevus,
        );
    }

    public function ajouterImprevu(Imprevu $imprevu): self
    {
        $imprevus = $this->imprevus;
        $imprevus[] = $imprevu;

        return new self(
            id: $this->id,
            chantierId: $this->chantierId,
            nom: $this->nom,
            mode: $this->mode,
            estimation: $this->estimation,
            reel: $this->reel,
            ordre: $this->ordre,
            imprevus: $imprevus,
        );
    }

    /**
     * Changer de mode change l'unité de l'estimation/réel : on les réinitialise
     * pour éviter une valeur dont le sens a silencieusement changé (cf ADR 0024).
     * Inchangé si le mode cible est identique.
     */
    public function changerMode(ModeFacturation $cible): self
    {
        if ($cible === $this->mode) {
            return $this;
        }

        return new self(
            id: $this->id,
            chantierId: $this->chantierId,
            nom: $this->nom,
            mode: $cible,
            estimation: null,
            reel: null,
            ordre: $this->ordre,
            imprevus: $this->imprevus,
        );
    }

    private static function nomValide(string $nom): string
    {
        $nom = trim($nom);

        if ($nom === '') {
            throw LotInvalideException::nomVide();
        }

        return $nom;
    }
}
