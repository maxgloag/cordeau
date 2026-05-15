<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\ValueObject;

use App\Domain\Chantier\ValueObject\ClientRef;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClientRefTest extends TestCase
{
    #[Test]
    public function il_se_construit_avec_des_valeurs_valides(): void
    {
        $id = Uuid::v7();
        $ref = new ClientRef(id: $id, nomCache: 'ACME SARL');

        self::assertTrue($id->equals($ref->id));
        self::assertSame('ACME SARL', $ref->nomCache);
    }

    #[Test]
    public function il_rejette_un_nom_vide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ClientRef(id: Uuid::v7(), nomCache: '');
    }

    #[Test]
    public function il_rejette_un_nom_uniquement_compose_d_espaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ClientRef(id: Uuid::v7(), nomCache: '   ');
    }

    #[Test]
    public function deux_refs_avec_le_meme_id_sont_egales(): void
    {
        $id = Uuid::v7();
        $a = new ClientRef(id: $id, nomCache: 'ACME');
        $b = new ClientRef(id: $id, nomCache: 'ACME Renommé');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function deux_refs_avec_des_ids_differents_ne_sont_pas_egales(): void
    {
        $a = new ClientRef(id: Uuid::v7(), nomCache: 'Client A');
        $b = new ClientRef(id: Uuid::v7(), nomCache: 'Client A');

        self::assertFalse($a->equals($b));
    }
}
