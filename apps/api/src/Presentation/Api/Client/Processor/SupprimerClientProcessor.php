<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Client\Repository\ClientRepository;
use App\Presentation\Api\Client\Resource\ClientResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;

/**
 * @implements ProcessorInterface<ClientResource, void>
 */
final class SupprimerClientProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(private readonly ClientRepository $repository)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $client = $this->repository->find($this->extractUuid($uriVariables));

        if ($client === null) {
            return;
        }

        $this->repository->remove($client);
    }
}
