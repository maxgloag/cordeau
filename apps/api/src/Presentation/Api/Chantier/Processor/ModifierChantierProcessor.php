<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\ModifierChantierUseCase;
use App\Entity\User;
use App\Presentation\Api\Chantier\Payload\ModifierChantierPayload;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use App\Presentation\Api\Support\ClientRefResolver;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<ModifierChantierPayload, ChantierResource>
 */
final class ModifierChantierProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly ModifierChantierUseCase $useCase,
        private readonly Security $security,
        private readonly ClientRefResolver $clientRefResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChantierResource
    {
        $existant = $context['previous_data'] ?? null;
        if (!$existant instanceof ChantierResource) {
            throw new \LogicException('previous_data manquant — provider mal configuré sur l\'opération PATCH.');
        }

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $client = $data->clientId !== null ? $this->clientRefResolver->resoudre($data->clientId, $user->id) : null;

        $id = $this->extractUuid($uriVariables);
        $chantier = $this->useCase->execute($id, $data->toAdresse($existant), $data->toSurface(), $client);

        return ChantierResource::fromDomain($chantier);
    }
}
