<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\ArchiverChantierUseCase;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;

/**
 * @implements ProcessorInterface<ChantierResource, void>
 */
final class ArchiverChantierProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(private readonly ArchiverChantierUseCase $useCase)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $this->useCase->execute($this->extractUuid($uriVariables));
    }
}
