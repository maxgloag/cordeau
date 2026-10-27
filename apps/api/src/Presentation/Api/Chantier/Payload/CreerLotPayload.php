<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Payload;

use App\Domain\Chantier\Enum\ModeFacturation;
use App\Domain\Chantier\ValueObject\Estimation;
use App\Presentation\Api\Support\BornesColonnes;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final class CreerLotPayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public readonly string $nom = '',
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'modes'])]
        public readonly string $mode = '',
        #[Assert\Range(min: 0, max: BornesColonnes::ENTIER_MAX)]
        public readonly int $ordre = 0,
        #[Assert\Range(min: 0, max: BornesColonnes::DECIMAL_12_2_MAX)]
        public readonly ?float $estimation = null,
        #[Assert\Uuid]
        public readonly ?string $uuid = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return array_map(static fn (ModeFacturation $mode): string => $mode->value, ModeFacturation::cases());
    }

    public function toMode(): ModeFacturation
    {
        return ModeFacturation::from($this->mode);
    }

    public function toEstimation(): ?Estimation
    {
        return $this->estimation !== null ? new Estimation($this->estimation) : null;
    }

    public function toId(): ?Uuid
    {
        return $this->uuid !== null ? Uuid::fromString($this->uuid) : null;
    }
}
