<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use App\Domain\Chantier\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use Symfony\Component\Validator\Constraints as Assert;

final class ModifierChantierPayload
{
    public function __construct(
        #[Assert\Length(max: 255)]
        #[Assert\NotBlank(allowNull: true)]
        public readonly ?string $adresseRue = null,
        #[Assert\Length(max: 20)]
        #[Assert\NotBlank(allowNull: true)]
        public readonly ?string $adresseCodePostal = null,
        #[Assert\Length(max: 255)]
        #[Assert\NotBlank(allowNull: true)]
        public readonly ?string $adresseVille = null,
        #[Assert\Length(exactly: 2)]
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
        public readonly ?string $adressePays = null,
        #[Assert\Positive]
        public readonly ?float $surfaceM2 = null,
    ) {
    }

    public function toAdresse(ChantierResource $existant): ?Adresse
    {
        $aucunChampAdresse = $this->adresseRue === null
            && $this->adresseCodePostal === null
            && $this->adresseVille === null
            && $this->adressePays === null;

        if ($aucunChampAdresse) {
            return null;
        }

        return new Adresse(
            rue: $this->adresseRue ?? $existant->adresseRue,
            codePostal: $this->adresseCodePostal ?? $existant->adresseCodePostal,
            ville: $this->adresseVille ?? $existant->adresseVille,
            pays: $this->adressePays ?? $existant->adressePays,
        );
    }

    public function toSurface(): ?Surface
    {
        return $this->surfaceM2 !== null ? new Surface($this->surfaceM2) : null;
    }
}
