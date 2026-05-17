<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineOAuthLoginCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineOAuthLoginCodeRepository::class)]
#[ORM\Table(name: 'oauth_login_code')]
class OAuthLoginCode
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public readonly Uuid $id;

    /** Code opaque envoyé dans l'URL de redirect (échangé contre les vrais tokens). */
    #[ORM\Column(length: 64, unique: true)]
    public readonly string $code;

    #[ORM\ManyToOne(targetEntity: AuthToken::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public readonly AuthToken $authToken;

    /** Token d'accès brut (selector.verifier) — stocké temporairement jusqu'à l'échange. */
    #[ORM\Column(length: 512)]
    public readonly string $tokenRaw;

    /** Refresh token brut — stocké temporairement jusqu'à l'échange. */
    #[ORM\Column(length: 255)]
    public readonly string $refreshTokenRaw;

    #[ORM\Column]
    public readonly \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $utiliseLe = null;

    #[ORM\Column]
    public readonly \DateTimeImmutable $creeLe;

    public function __construct(
        Uuid $id,
        string $code,
        AuthToken $authToken,
        string $tokenRaw,
        string $refreshTokenRaw,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->authToken = $authToken;
        $this->tokenRaw = $tokenRaw;
        $this->refreshTokenRaw = $refreshTokenRaw;
        $this->expiresAt = $expiresAt;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function estValide(): bool
    {
        return $this->utiliseLe === null && $this->expiresAt > new \DateTimeImmutable();
    }

    public function utiliser(): void
    {
        $this->utiliseLe = new \DateTimeImmutable();
    }
}
