<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\CreerChantierUseCase;
use App\Domain\Chantier\Exception\ChantierDejaExistantException;
use App\Entity\User;
use App\Presentation\Api\Chantier\Payload\CreerChantierPayload;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use App\Presentation\Api\Support\ClientRefResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CreerChantierPayload, ChantierResource>
 */
final class CreerChantierProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CreerChantierUseCase $useCase,
        private readonly Security $security,
        private readonly ClientRefResolver $clientRefResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChantierResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $client = $data->clientId !== null ? $this->clientRefResolver->resoudre($data->clientId, $user->id) : null;
        $id = $data->uuid !== null ? Uuid::fromString($data->uuid) : null;

        try {
            $chantier = $this->useCase->execute($user->id, $data->toAdresse(), $data->toSurface(), $client, $id);
        } catch (ChantierDejaExistantException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return ChantierResource::fromDomain($chantier);
    }
}
