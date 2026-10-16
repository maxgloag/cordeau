<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\AccesProprietaire;
use App\Entity\User;
use App\Presentation\Api\Chantier\Resource\TacheResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Tâche d'un lot de l'utilisateur, sinon 404 (TacheIntrouvableException) : PATCH et DELETE
 * passent par ici avant leur processor.
 *
 * @implements ProviderInterface<TacheResource>
 */
final class TacheItemProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly AccesProprietaire $acces,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TacheResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return TacheResource::fromDomain($this->acces->tache($this->extractUuid($uriVariables), $user->id));
    }
}
