<?php

declare(strict_types=1);

namespace App\Presentation\Api\Chantier\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Chantier\AccesProprietaire;
use App\Entity\User;
use App\Presentation\Api\Chantier\Resource\LotResource;
use App\Presentation\Api\Support\UuidUriVariableExtractor;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Lot d'un chantier de l'utilisateur, sinon 404 (LotIntrouvableException) : PATCH et DELETE
 * passent par ici avant leur processor.
 *
 * @implements ProviderInterface<LotResource>
 */
final class LotItemProvider implements ProviderInterface
{
    use UuidUriVariableExtractor;

    public function __construct(
        private readonly AccesProprietaire $acces,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LotResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return LotResource::fromDomain($this->acces->lot($this->extractUuid($uriVariables), $user->id));
    }
}
