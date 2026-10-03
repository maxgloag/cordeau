<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\UseCase\ObtenirChantierUseCase;
use App\Entity\User;
use App\Photo\Repository\PhotoRepository;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<ChantierResource>
 */
final class ChantierItemProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly ObtenirChantierUseCase $useCase,
        private readonly PhotoRepository $photoRepository,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ChantierResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $chantier = $this->useCase->execute($this->extractUuid($uriVariables), $user->id);
        if ($chantier === null) {
            return null;
        }

        $photosCount = $this->photoRepository->countByChantierIds([$chantier->id])[$chantier->id->toRfc4122()] ?? 0;

        return ChantierResource::fromDomain($chantier, $photosCount);
    }
}
