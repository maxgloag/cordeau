<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\SupprimerTacheUseCase;
use App\Entity\User;
use App\Presentation\Api\Chantier\Resource\TacheResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<TacheResource, void>
 */
final class SupprimerTacheProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly SupprimerTacheUseCase $useCase,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $this->useCase->execute($this->extractUuid($uriVariables), $user->id);
    }
}
