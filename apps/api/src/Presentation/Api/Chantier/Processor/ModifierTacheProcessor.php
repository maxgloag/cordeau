<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\ModifierTacheUseCase;
use App\Entity\User;
use App\Presentation\Api\Chantier\Payload\ModifierTachePayload;
use App\Presentation\Api\Chantier\Resource\TacheResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<ModifierTachePayload, TacheResource>
 */
final class ModifierTacheProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly ModifierTacheUseCase $useCase,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TacheResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $tache = $this->useCase->execute($this->extractUuid($uriVariables), $user->id, $data->faite, $data->libelle);

        return TacheResource::fromDomain($tache);
    }
}
