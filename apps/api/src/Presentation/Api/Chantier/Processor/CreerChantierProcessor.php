<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\CreerChantierUseCase;
use App\Client\Repository\ClientRepository;
use App\Domain\Chantier\ValueObject\ClientRef;
use App\Entity\User;
use App\Presentation\Api\Chantier\Payload\CreerChantierPayload;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CreerChantierPayload, ChantierResource>
 */
final class CreerChantierProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CreerChantierUseCase $useCase,
        private readonly Security $security,
        private readonly ClientRepository $clientRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChantierResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $client = $data->clientId !== null ? $this->resolveClientRef($data->clientId, $user->id) : null;

        $chantier = $this->useCase->execute($user->id, $data->toAdresse(), $data->toSurface(), $client);

        return ChantierResource::fromDomain($chantier);
    }

    private function resolveClientRef(string $clientId, Uuid $proprietaireId): ClientRef
    {
        $client = $this->clientRepository->find(Uuid::fromString($clientId));

        if ($client === null || !$client->proprietaire->id->equals($proprietaireId)) {
            throw new UnprocessableEntityHttpException('Client introuvable ou non autorisé.');
        }

        return new ClientRef(id: $client->id, nomCache: $client->nom);
    }
}
