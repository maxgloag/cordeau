<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Domain\Chantier\Enum\StatutChantier;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\ChantierDoctrineEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ChantierDoctrineEntity>
 */
final class ChantierFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ChantierDoctrineEntity::class;
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id' => Uuid::v7(),
            'adresseRue' => self::faker()->streetAddress(),
            'adresseCodePostal' => self::faker()->numerify('#####'),
            'adresseVille' => self::faker()->city(),
            'adressePays' => 'FR',
            'surfaceM2' => null,
            'statut' => StatutChantier::EN_PREPARATION,
            'creeLe' => $now,
            'modifieLe' => $now,
        ];
    }
}
