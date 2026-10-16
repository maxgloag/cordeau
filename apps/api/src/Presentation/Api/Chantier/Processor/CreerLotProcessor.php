<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\CreerLotUseCase;
use App\Domain\Chantier\Exception\LotDejaExistantException;
use App\Entity\User;
use App\Presentation\Api\Chantier\Payload\CreerLotPayload;
use App\Presentation\Api\Chantier\Resource\LotResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

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

        try {
            $lot = $this->useCase->execute(
                $this->extractUuid($uriVariables, 'chantierId'),
                $user->id,
                $data->nom,
                $data->toMode(),
                $data->ordre,
                $data->toEstimation(),
                $data->uuid !== null ? Uuid::fromString($data->uuid) : null,
            );
        } catch (LotDejaExistantException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return LotResource::fromDomain($lot);
    }
}
