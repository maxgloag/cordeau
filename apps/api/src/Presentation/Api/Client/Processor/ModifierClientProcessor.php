<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Client\Repository\ClientRepository;
use App\Client\ValueObject\Telephone;
use App\Presentation\Api\Client\Payload\ModifierClientPayload;
use App\Presentation\Api\Client\Resource\ClientResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ModifierClientPayload, ClientResource>
 */
final class ModifierClientProcessor implements ProcessorInterface
{
    use UuidUriVariableExtractor;

    public function __construct(private readonly ClientRepository $repository)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientResource
    {
        $client = $this->repository->find($this->extractUuid($uriVariables));

        if ($client === null) {
            throw new NotFoundHttpException();
        }

        if ($data->nom !== null) {
            $client->nom = $data->nom;
        }

        if ($data->email !== null) {
            $client->email = $data->email;
        }

        if ($data->telephone !== null) {
            $client->telephone = (new Telephone($data->telephone))->valeur;
        }

        if ($data->adresseRue !== null) {
            $client->adresseRue = $data->adresseRue;
        }

        if ($data->adresseCodePostal !== null) {
            $client->adresseCodePostal = $data->adresseCodePostal;
        }

        if ($data->adresseVille !== null) {
            $client->adresseVille = $data->adresseVille;
        }

        if ($data->adressePays !== null) {
            $client->adressePays = $data->adressePays;
        }

        if ($data->notes !== null) {
            $client->notes = $data->notes;
        }

        $client->modifieLe = new \DateTimeImmutable();

        $this->repository->save($client);

        return ClientResource::fromEntity($client);
    }
}
