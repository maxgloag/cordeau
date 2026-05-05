<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\Enum;

use App\Domain\Chantier\Enum\StatutChantier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatutChantierTest extends TestCase
{
    #[Test]
    public function archive_est_un_statut_terminal(): void
    {
        self::assertTrue(StatutChantier::ARCHIVE->estTerminal());
    }

    #[Test]
    public function les_autres_statuts_ne_sont_pas_terminaux(): void
    {
        self::assertFalse(StatutChantier::EN_PREPARATION->estTerminal());
        self::assertFalse(StatutChantier::EN_COURS->estTerminal());
        self::assertFalse(StatutChantier::TERMINE->estTerminal());
    }

    #[Test]
    public function les_valeurs_serialisees_sont_stables(): void
    {
        self::assertSame('en_preparation', StatutChantier::EN_PREPARATION->value);
        self::assertSame('en_cours', StatutChantier::EN_COURS->value);
        self::assertSame('termine', StatutChantier::TERMINE->value);
        self::assertSame('archive', StatutChantier::ARCHIVE->value);
    }
}
