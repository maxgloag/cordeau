<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineAuthTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineAuthTokenRepository::class)]
#[ORM\Table(name: 'auth_token')]
#[ORM\Index(name: 'idx_auth_token_selector', columns: ['selector'])]
class AuthToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public readonly Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public readonly User $utilisateur;

    /** Partie publique du token (32 hex chars), indexée pour la recherche. */
    #[ORM\Column(length: 32)]
    public readonly string $selector;

    /** Hash bcrypt de la partie secrète du token. */
    #[ORM\Column(length: 255)]
    public string $verifierHash;

    #[ORM\Column(nullable: true, length: 255)]
    public ?string $refreshTokenHash;

    #[ORM\Column]
    public \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $refreshExpiresAt;

    #[ORM\Column(nullable: true, length: 255)]
    public ?string $deviceInfo;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $revokedAt;

    #[ORM\Column]
    public readonly \DateTimeImmutable $creeLe;

    public function __construct(
        Uuid $id,
        User $utilisateur,
        string $selector,
        string $verifierHash,
        ?string $refreshTokenHash,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $refreshExpiresAt = null,
        ?string $deviceInfo = null,
    ) {
        $this->id = $id;
        $this->utilisateur = $utilisateur;
        $this->selector = $selector;
        $this->verifierHash = $verifierHash;
        $this->refreshTokenHash = $refreshTokenHash;
        $this->expiresAt = $expiresAt;
        $this->refreshExpiresAt = $refreshExpiresAt;
        $this->deviceInfo = $deviceInfo;
        $this->revokedAt = null;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function estValide(): bool
    {
        return $this->revokedAt === null && $this->expiresAt > new \DateTimeImmutable();
    }

    public function revoquer(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }
}
