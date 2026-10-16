<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\CreerTacheUseCase;
use App\Domain\Chantier\Exception\TacheDejaExistantException;
use App\Entity\User;
use App\Presentation\Api\Chantier\Payload\CreerTachePayload;
use App\Presentation\Api\Chantier\Resource\TacheResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CreerTachePayload, TacheResource>
 */
final class CreerTacheProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly CreerTacheUseCase $useCase,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TacheResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        try {
            $tache = $this->useCase->execute(
                $this->extractUuid($uriVariables, 'lotId'),
                $user->id,
                $data->libelle,
                $data->ordre,
                $data->uuid !== null ? Uuid::fromString($data->uuid) : null,
            );
        } catch (TacheDejaExistantException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return TacheResource::fromDomain($tache);
    }
}
