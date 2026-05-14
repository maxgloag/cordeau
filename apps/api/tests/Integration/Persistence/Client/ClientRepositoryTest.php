<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence\Client;

use App\Client\Entity\Client;
use App\Client\Repository\ClientRepository;
use App\Tests\Factory\ClientFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ClientRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private ClientRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $repository = $container->get(ClientRepository::class);
        \assert($repository instanceof ClientRepository);
        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    #[Test]
    public function il_persiste_un_client_et_le_retrouve_par_id(): void
    {
        $client = ClientFactory::createOne([
            'nom' => 'ACME SARL',
            'email' => 'contact@acme.fr',
            'telephone' => '+33612345678',
            'adresseRue' => '10 rue de la Paix',
            'adresseCodePostal' => '75002',
            'adresseVille' => 'Paris',
        ])->_real();

        $this->entityManager->clear();

        $retrouve = $this->repository->find($client->id);

        self::assertNotNull($retrouve);
        self::assertSame('ACME SARL', $retrouve->nom);
        self::assertSame('contact@acme.fr', $retrouve->email);
        self::assertSame('+33612345678', $retrouve->telephone);
        self::assertSame('Paris', $retrouve->adresseVille);
    }

    #[Test]
    public function il_retourne_null_si_l_id_n_existe_pas(): void
    {
        $resultat = $this->repository->find(Uuid::v7());

        self::assertNull($resultat);
    }

    #[Test]
    public function il_liste_les_clients_d_un_user_tries_par_nom(): void
    {
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();

        ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'Charlie']);
        ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'Alpha']);
        ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'Bravo']);
        ClientFactory::createOne(['proprietaire' => $autreUser, 'nom' => 'Autre']);

        $clients = $this->repository->findAllForUser($user->id);

        self::assertCount(3, $clients);
        self::assertSame(['Alpha', 'Bravo', 'Charlie'], array_map(static fn (Client $c): string => $c->nom, $clients));
    }

    #[Test]
    public function il_isole_les_clients_par_proprietaire(): void
    {
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();

        ClientFactory::createMany(2, ['proprietaire' => $user]);
        ClientFactory::createOne(['proprietaire' => $autreUser]);

        self::assertCount(2, $this->repository->findAllForUser($user->id));
        self::assertCount(1, $this->repository->findAllForUser($autreUser->id));
    }

    #[Test]
    public function il_met_a_jour_un_client_existant_via_save(): void
    {
        $client = ClientFactory::createOne(['nom' => 'Ancien nom'])->_real();
        $client->nom = 'Nouveau nom';
        $client->modifieLe = new \DateTimeImmutable();

        $this->repository->save($client);
        $this->entityManager->clear();

        $retrouve = $this->repository->find($client->id);

        self::assertNotNull($retrouve);
        self::assertSame('Nouveau nom', $retrouve->nom);
    }

    #[Test]
    public function il_supprime_un_client_via_remove(): void
    {
        $client = ClientFactory::createOne()->_real();

        $this->repository->remove($client);
        $this->entityManager->clear();

        self::assertNull($this->repository->find($client->id));
    }
}
