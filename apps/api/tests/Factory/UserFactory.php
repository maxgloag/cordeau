<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\User;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v7(),
            'email' => self::faker()->unique()->safeEmail(),
            'motDePasseHash' => password_hash('Password1', PASSWORD_BCRYPT, ['cost' => 4]),
            'creeLe' => new \DateTimeImmutable(),
            'modifieLe' => new \DateTimeImmutable(),
        ];
    }
}
