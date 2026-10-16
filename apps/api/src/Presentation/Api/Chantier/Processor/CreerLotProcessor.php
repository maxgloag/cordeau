<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\CreerLotUseCase;
use App\Entity\User;
use App\Presentation\Api\Chantier\Payload\CreerLotPayload;
use App\Presentation\Api\Chantier\Resource\LotResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<CreerLotPayload, LotResource>
 */
final class CreerLotProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly CreerLotUseCase $useCase,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LotResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $lot = $this->useCase->execute(
            $this->extractUuid($uriVariables, 'chantierId'),
            $user->id,
            $data->nom,
            $data->toMode(),
            $data->ordre,
            $data->toEstimation(),
            $data->toId(),
        );

        return LotResource::fromDomain($lot);
    }
}
