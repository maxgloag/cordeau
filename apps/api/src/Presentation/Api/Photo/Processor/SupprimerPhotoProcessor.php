<?php

declare(strict_types=1);

namespace App\Presentation\Api\Photo\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/** @implements ProcessorInterface<mixed, void> */
final class SupprimerPhotoProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void {}
}
