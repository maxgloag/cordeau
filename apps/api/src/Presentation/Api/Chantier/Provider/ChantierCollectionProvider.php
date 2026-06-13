<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\UseCase\ListerChantierUseCase;
use App\Domain\Chantier\Entity\Chantier;
use App\Entity\User;
use App\Photo\Repository\PhotoRepository;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ChantierResource>
 */
final class ChantierCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ListerChantierUseCase $useCase,
        private readonly PhotoRepository $photoRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return list<ChantierResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $chantiers = $this->useCase->execute($user->id);
        $photosCounts = $this->photoRepository->countByChantierIds(
            array_map(static fn (Chantier $chantier): Uuid => $chantier->id, $chantiers),
        );

        return array_map(
            static fn (Chantier $chantier): ChantierResource => ChantierResource::fromDomain(
                $chantier,
                $photosCounts[$chantier->id->toRfc4122()] ?? 0,
            ),
            $chantiers,
        );
    }
}
