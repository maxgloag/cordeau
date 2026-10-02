<?php

declare(strict_types=1);

namespace App\Domain\Devis\Enum;

enum RegimeTva: string
{
    case NORMAL = 'NORMAL';
    /** Auto-entrepreneur, art. 293 B du CGI : pas de TVA facturée. */
    case FRANCHISE_EN_BASE = 'FRANCHISE_EN_BASE';

    public const string MENTION_FRANCHISE_EN_BASE = 'TVA non applicable, art. 293 B du CGI';

    /**
     * En régime normal tous les taux sont admis, y compris 0 (ex. autoliquidation
     * de la sous-traitance BTP). En franchise en base, seul le taux 0.
     */
    public function autorise(TauxTva $taux): bool
    {
        return match ($this) {
            self::NORMAL => true,
            self::FRANCHISE_EN_BASE => $taux === TauxTva::ZERO,
        };
    }

    public function factureLaTva(): bool
    {
        return $this === self::NORMAL;
    }

    public function mentionTva(): ?string
    {
        return match ($this) {
            self::NORMAL => null,
            self::FRANCHISE_EN_BASE => self::MENTION_FRANCHISE_EN_BASE,
        };
    }
}
