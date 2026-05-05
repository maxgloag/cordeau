<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\CreerChantierUseCase;
use App\Domain\Chantier\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
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
        $adresse = new Adresse(
            rue: $data->adresseRue,
            codePostal: $data->adresseCodePostal,
            ville: $data->adresseVille,
            pays: $data->adressePays,
        );

        $surface = $data->surfaceM2 !== null ? new Surface($data->surfaceM2) : null;

        $chantier = $this->useCase->execute($adresse, $surface);

        return ChantierResource::fromDomain($chantier);
    }
}
