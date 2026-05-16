<?php

declare(strict_types=1);

namespace App\Auth\Entity;

use App\Auth\Repository\OAuthAccountRepository;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OAuthAccountRepository::class)]
#[ORM\Table(name: 'oauth_account')]
#[ORM\UniqueConstraint(name: 'uniq_oauth_account_provider_user', columns: ['provider', 'provider_user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_oauth_account_user_provider', columns: ['user_id', 'provider'])]
class OAuthAccount
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: 'string', length: 32)]
    public string $provider;

    #[ORM\Column(type: 'string', length: 255, name: 'provider_user_id')]
    public string $providerUserId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $email;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    public \DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $id,
        User $user,
        string $provider,
        string $providerUserId,
        ?string $email,
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->provider = $provider;
        $this->providerUserId = $providerUserId;
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
    }
}
