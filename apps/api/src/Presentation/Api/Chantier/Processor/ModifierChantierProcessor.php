<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\ModifierChantierUseCase;
use App\Presentation\Api\Chantier\Payload\ModifierChantierPayload;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;

/**
 * @implements ProcessorInterface<ModifierChantierPayload, ChantierResource>
 */
final class ModifierChantierProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(private readonly ModifierChantierUseCase $useCase)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChantierResource
    {
        $existant = $context['previous_data'] ?? null;
        if (!$existant instanceof ChantierResource) {
            throw new \LogicException('previous_data manquant — provider mal configuré sur l\'opération PATCH.');
        }

        $id = $this->extractUuid($uriVariables);
        $chantier = $this->useCase->execute($id, $data->toAdresse($existant), $data->toSurface());

        return ChantierResource::fromDomain($chantier);
    }
}
