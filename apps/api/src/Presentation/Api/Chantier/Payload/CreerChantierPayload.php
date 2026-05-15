<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use App\Shared\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use Symfony\Component\Validator\Constraints as Assert;

final class CreerChantierPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $adresseRue = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public readonly string $adresseCodePostal = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $adresseVille = '',
        #[Assert\Length(exactly: 2)]
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
        public readonly string $adressePays = 'FR',
        #[Assert\Positive]
        public readonly ?float $surfaceM2 = null,
        #[Assert\Uuid]
        public readonly ?string $clientId = null,
        #[Assert\Uuid]
        public readonly ?string $uuid = null,
    ) {
    }

    public function toAdresse(): Adresse
    {
        return new Adresse(
            rue: $this->adresseRue,
            codePostal: $this->adresseCodePostal,
            ville: $this->adresseVille,
            pays: $this->adressePays,
        );
    }

    public function toSurface(): ?Surface
    {
        return $this->surfaceM2 !== null ? new Surface($this->surfaceM2) : null;
    }
}
