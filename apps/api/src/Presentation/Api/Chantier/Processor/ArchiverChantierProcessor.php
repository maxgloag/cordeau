<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Chantier\UseCase\ArchiverChantierUseCase;
use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use App\Presentation\Api\Chantier\Resource\ChantierResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ChantierResource, void>
 */
final class ArchiverChantierProcessor implements ProcessorInterface
{
    public function __construct(private readonly ArchiverChantierUseCase $useCase)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $rawId = $uriVariables['id'] ?? null;
        \assert(\is_string($rawId));
        $id = Uuid::fromString($rawId);

        try {
            $this->useCase->execute($id);
        } catch (ChantierIntrouvableException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
}
