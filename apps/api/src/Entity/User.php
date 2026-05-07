<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineUserRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\UniqueConstraint(name: 'uniq_utilisateur_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    public string $email;

    #[ORM\Column]
    public string $motDePasseHash;

    #[ORM\Column]
    public \DateTimeImmutable $creeLe;

    #[ORM\Column]
    public \DateTimeImmutable $modifieLe;

    public function __construct(Uuid $id, string $email, string $motDePasseHash)
    {
        $this->id = $id;
        $this->email = $email;
        $this->motDePasseHash = $motDePasseHash;
        $this->creeLe = new \DateTimeImmutable();
        $this->modifieLe = new \DateTimeImmutable();
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        \assert($this->email !== '');

        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->motDePasseHash;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
