<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\RemiseSuperieureAuTotalException;

final readonly class RemiseGlobaleMontant implements RemiseGlobale
{
    /**
     * Échelle de troncature du quotient M × base / total avant arrondi au centime.
     * Pour un quotient q ≥ 0, l'arrondi demi-supérieur au centime ne dépend que de
     * floor(q × 1000) : toute troncature à 3 décimales ou plus donne donc exactement
     * le même arrondi que le quotient rationnel exact. 10 laisse de la marge.
     */
    private const int ECHELLE_QUOTIENT = 10;

    public function __construct(
        public Montant $montant,
    ) {
    }

    /**
     * part = arrondi(M × base / total_ht_brut) pour chaque base ; l'écart M − Σ part
     * (positif ou négatif) est reporté sur la part de la base la plus élevée, à égalité
     * celle du taux le plus élevé. La somme des parts vaut toujours M.
     *
     * @throws RemiseSuperieureAuTotalException
     */
    public function ventiler(array $bases): array
    {
        $total = array_reduce($bases, static fn (Montant $somme, BaseHt $base): Montant => $somme->plus($base->montant), Montant::zero());
        if ($this->depasse($total)) {
            throw RemiseSuperieureAuTotalException::depasseLeTotal($total);
        }

        // Total nul ⇒ M nul (sinon depasse()) : toutes les parts valent 0.
        $parts = array_map(
            fn (BaseHt $base): Montant => $total->estNul()
                ? Montant::zero()
                : Montant::arrondi($this->montant->valeur->mul($base->montant->valeur)->div($total->valeur, self::ECHELLE_QUOTIENT)),
            $bases,
        );

        $ecart = $this->montant->moins(array_reduce($parts, static fn (Montant $somme, Montant $part): Montant => $somme->plus($part), Montant::zero()));
        $indexBasePrincipale = self::indexDeLaBasePrincipale($bases);

        return array_map(
            static fn (int $index, Montant $part): Montant => $index === $indexBasePrincipale ? $part->plus($ecart) : $part,
            array_keys($parts),
            $parts,
        );
    }

    public function depasse(Montant $totalHtBrut): bool
    {
        return $this->montant->estSuperieurA($totalHtBrut);
    }

    /**
     * @param non-empty-list<BaseHt> $bases
     */
    private static function indexDeLaBasePrincipale(array $bases): int
    {
        $index = 0;
        foreach ($bases as $i => $base) {
            $principale = $bases[$index];
            $plusElevee = $base->montant->estSuperieurA($principale->montant);
            $egaleDeTauxPlusEleve = $base->montant->estEgalA($principale->montant) && $base->taux->compare($principale->taux) > 0;
            if ($plusElevee || $egaleDeTauxPlusEleve) {
                $index = $i;
            }
        }

        return $index;
    }
}
