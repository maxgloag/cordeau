<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\ModifierChantierUseCase;
use App\Domain\Chantier\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use App\Presentation\Api\Chantier\Payload\ModifierChantierPayload;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use App\Domain\Chantier\Exception\ChantierIntrouvableException;

/**
 * @implements ProcessorInterface<ModifierChantierPayload, ChantierResource>
 */
final class ModifierChantierProcessor implements ProcessorInterface
{
    public function __construct(private readonly ModifierChantierUseCase $useCase)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChantierResource
    {
        $rawId = $uriVariables['id'] ?? null;
        \assert(\is_string($rawId));
        $id = Uuid::fromString($rawId);

        $nouvelleAdresse = null;
        if (
            $data->adresseRue !== null
            || $data->adresseCodePostal !== null
            || $data->adresseVille !== null
            || $data->adressePays !== null
        ) {
            $existant = $context['previous_data'] ?? null;
            if (!$existant instanceof ChantierResource) {
                throw new NotFoundHttpException();
            }

            $nouvelleAdresse = new Adresse(
                rue: $data->adresseRue ?? $existant->adresseRue,
                codePostal: $data->adresseCodePostal ?? $existant->adresseCodePostal,
                ville: $data->adresseVille ?? $existant->adresseVille,
                pays: $data->adressePays ?? $existant->adressePays,
            );
        }

        $nouvelleSurface = $data->surfaceM2 !== null ? new Surface($data->surfaceM2) : null;

        try {
            $chantier = $this->useCase->execute($id, $nouvelleAdresse, $nouvelleSurface);
        } catch (ChantierIntrouvableException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return ChantierResource::fromDomain($chantier);
    }
}
