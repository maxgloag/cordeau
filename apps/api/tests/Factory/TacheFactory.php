<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\TacheDoctrineEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentProxyObjectFactory<TacheDoctrineEntity>
 */
final class TacheFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return TacheDoctrineEntity::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v7(),
            'lotId' => lazy(static fn (): Uuid => LotFactory::createOne()->id),
            'libelle' => self::faker()->sentence(3),
            'faite' => false,
            'faiteLe' => null,
            'ordre' => 0,
        ];
    }
}
