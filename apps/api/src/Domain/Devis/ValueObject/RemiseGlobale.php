<?php

declare(strict_types=1);

namespace App\Domain\Devis\ValueObject;

/**
 * Remise appliquée à l'ensemble du devis, ventilée sur les bases HT par taux
 * (la TVA se calcule ensuite sur chaque base nette).
 */
interface RemiseGlobale
{
    /**
     * @param non-empty-list<BaseHt> $bases bases HT brutes par taux
     *
     * @return non-empty-list<Montant> part de remise de chaque base, dans le même ordre que $bases
     */
    public function ventiler(array $bases): array;

    public function depasse(Montant $totalHtBrut): bool;
}
