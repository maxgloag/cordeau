<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Client\Exception\TelephoneInvalideException;
use App\Client\Repository\ClientRepository;
use App\Client\ValueObject\Telephone;
use App\Presentation\Api\Client\Payload\ModifierClientPayload;
use App\Presentation\Api\Client\Resource\ClientResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use App\Shared\ValueObject\Adresse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
            try {
                $client->telephone = (new Telephone($data->telephone))->valeur;
            } catch (TelephoneInvalideException $e) {
                throw new UnprocessableEntityHttpException($e->getMessage());
            }
        }

        // L'adresse résultante est validée par le value object partagé, et seulement si un de ses champs
        // change : un client déjà enregistré avec une ancienne adresse reste modifiable (#194).
        if ($data->adresseRue !== null || $data->adresseCodePostal !== null || $data->adresseVille !== null || $data->adressePays !== null) {
            $adresse = new Adresse(
                rue: $data->adresseRue ?? $client->adresseRue,
                codePostal: $data->adresseCodePostal ?? $client->adresseCodePostal,
                ville: $data->adresseVille ?? $client->adresseVille,
                pays: $data->adressePays ?? $client->adressePays,
            );
            $client->adresseRue = $adresse->rue;
            $client->adresseCodePostal = $adresse->codePostal;
            $client->adresseVille = $adresse->ville;
            $client->adressePays = $adresse->pays;
        }

        if ($data->notes !== null) {
            $client->notes = $data->notes;
        }

        $client->modifieLe = new \DateTimeImmutable();

        $this->repository->save($client);

        return ClientResource::fromEntity($client);
    }
}
