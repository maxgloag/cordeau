<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\CreerChantierUseCase;
use App\Presentation\Api\Chantier\Payload\CreerChantierPayload;
use App\Presentation\Api\Chantier\Resource\ChantierResource;

/**
 * @implements ProcessorInterface<CreerChantierPayload, ChantierResource>
 */
final class CreerChantierProcessor implements ProcessorInterface
{
    public function __construct(private readonly CreerChantierUseCase $useCase)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChantierResource
    {
        $chantier = $this->useCase->execute($data->toAdresse(), $data->toSurface());

        return ChantierResource::fromDomain($chantier);
    }
}
