<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Client\Entity\Client;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Client>
 */
final class ClientFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Client::class;
    }

    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            'id' => Uuid::v7(),
            'proprietaire' => UserFactory::new(),
            'nom' => self::faker()->company(),
            'email' => self::faker()->companyEmail(),
            'telephone' => '+336' . self::faker()->numerify('########'),
            'adresseRue' => self::faker()->streetAddress(),
            'adresseCodePostal' => self::faker()->numerify('#####'),
            'adresseVille' => self::faker()->city(),
            'adressePays' => 'FR',
            'notes' => null,
            'creeLe' => $now,
            'modifieLe' => $now,
        ];
    }
}
