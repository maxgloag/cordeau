<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use App\Domain\Chantier\Enum\ModeFacturation;
use App\Domain\Chantier\ValueObject\Estimation;
use App\Domain\Chantier\ValueObject\Reel;
use App\Presentation\Api\Support\BornesColonnes;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Changer le mode réinitialise l'estimation et le réel (ADR 0024).
 */
final class ModifierLotPayload
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public readonly ?string $nom = null,
        #[Assert\Choice(callback: [CreerLotPayload::class, 'modes'])]
        public readonly ?string $mode = null,
        #[Assert\Range(min: 0, max: BornesColonnes::DECIMAL_12_2_MAX)]
        public readonly ?float $estimation = null,
        #[Assert\Range(min: 0, max: BornesColonnes::DECIMAL_12_2_MAX)]
        public readonly ?float $reel = null,
    ) {
    }

    public function toMode(): ?ModeFacturation
    {
        return $this->mode !== null ? ModeFacturation::from($this->mode) : null;
    }

    public function toEstimation(): ?Estimation
    {
        return $this->estimation !== null ? new Estimation($this->estimation) : null;
    }

    public function toReel(): ?Reel
    {
        return $this->reel !== null ? new Reel($this->reel) : null;
    }
}
