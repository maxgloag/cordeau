<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Enum\TauxTva;
use App\Domain\Devis\Exception\LignesDevisVidesException;

/**
 * Lignes d'un devis, dans l'ordre de saisie. Jamais vide.
 */
final readonly class LignesDevis
{
    /** @var non-empty-list<LigneDevis> */
    public array $lignes;

    /**
     * @param list<LigneDevis> $lignes
     *
     * @throws LignesDevisVidesException
     */
    public function __construct(array $lignes)
    {
        if ($lignes === []) {
            throw LignesDevisVidesException::aucuneLigne();
        }

        $this->lignes = $lignes;
    }

    /**
     * @return non-empty-list<Montant> montant HT de chaque ligne, dans l'ordre de saisie
     */
    public function montantsHt(): array
    {
        return array_map(static fn (LigneDevis $ligne): Montant => $ligne->montantHt(), $this->lignes);
    }

    public function totalHtBrut(): Montant
    {
        return array_reduce($this->montantsHt(), static fn (Montant $total, Montant $montant): Montant => $total->plus($montant), Montant::zero());
    }

    /**
     * @return non-empty-list<BaseHt> une base par taux présent dans les lignes, par taux croissant
     *                                (un taux présent dont la base est nulle figure quand même)
     */
    public function basesHt(): array
    {
        $bases = [];
        foreach (TauxTva::parOrdreCroissant() as $taux) {
            $lignesDuTaux = array_filter($this->lignes, static fn (LigneDevis $ligne): bool => $ligne->tauxTva === $taux);
            if ($lignesDuTaux === []) {
                continue;
            }

            $bases[] = new BaseHt(
                $taux,
                array_reduce($lignesDuTaux, static fn (Montant $base, LigneDevis $ligne): Montant => $base->plus($ligne->montantHt()), Montant::zero()),
            );
        }

        if ($bases === []) {
            // Inatteignable : au moins une ligne, et chaque ligne a un taux de TauxTva::cases().
            throw new \LogicException('Aucune base HT calculée pour des lignes non vides.');
        }

        return $bases;
    }
}
