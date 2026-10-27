<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Domain\Chantier\Enum\ModeFacturation;
use App\Infrastructure\Persistence\Doctrine\Chantier\Entity\LotDoctrineEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentProxyObjectFactory<LotDoctrineEntity>
 */
final class LotFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return LotDoctrineEntity::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v7(),
            'chantierId' => lazy(static fn (): Uuid => ChantierFactory::createOne()->id),
            'nom' => self::faker()->words(2, true),
            'mode' => ModeFacturation::FORFAIT,
            'estimation' => null,
            'reel' => null,
            'ordre' => 0,
            'imprevus' => [],
        ];
    }
}
