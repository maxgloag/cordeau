<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence\Chantier;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Enum\StatutChantier;
use App\Domain\Chantier\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use App\Infrastructure\Persistence\Doctrine\Chantier\Repository\DoctrineChantierRepository;
use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineChantierRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineChantierRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $this->entityManager = $entityManager;
        $this->repository = new DoctrineChantierRepository($entityManager);
    }

    #[Test]
    public function il_persiste_un_chantier_et_le_retrouve_par_id(): void
    {
        $user = UserFactory::createOne();
        $adresse = new Adresse('1 rue Test', '75001', 'Paris');
        $surface = new Surface(42.5);
        $chantier = Chantier::creer($user->id, $adresse, $surface);

        $this->repository->save($chantier);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($chantier->id);

        self::assertNotNull($retrouve);
        self::assertTrue($chantier->id->equals($retrouve->id));
        self::assertTrue($adresse->equals($retrouve->adresse));
        self::assertNotNull($retrouve->surface);
        self::assertSame(42.5, $retrouve->surface->valeurM2);
        self::assertSame(StatutChantier::EN_PREPARATION, $retrouve->statut);
        self::assertTrue($user->id->equals($retrouve->proprietaireId));
    }

    #[Test]
    public function il_persiste_un_chantier_sans_surface(): void
    {
        $user = UserFactory::createOne();
        $chantier = Chantier::creer($user->id, new Adresse('2 avenue', '69002', 'Lyon'));

        $this->repository->save($chantier);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($chantier->id);

        self::assertNotNull($retrouve);
        self::assertNull($retrouve->surface);
    }

    #[Test]
    public function il_retourne_null_si_l_id_n_existe_pas(): void
    {
        $resultat = $this->repository->findById(Uuid::v7());

        self::assertNull($resultat);
    }

    #[Test]
    public function il_met_a_jour_un_chantier_existant_lors_du_save(): void
    {
        $user = UserFactory::createOne();
        $chantier = Chantier::creer($user->id, new Adresse('3 cours', '33000', 'Bordeaux'));
        $this->repository->save($chantier);

        $modifie = $chantier->passerEnCours();
        $this->repository->save($modifie);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($chantier->id);

        self::assertNotNull($retrouve);
        self::assertSame(StatutChantier::EN_COURS, $retrouve->statut);
    }

    #[Test]
    public function il_liste_les_chantiers_du_user(): void
    {
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();

        ChantierFactory::createMany(2, ['proprietaire' => $user]);
        ChantierFactory::createOne(['proprietaire' => $autreUser]);

        $chantiers = $this->repository->findAllForUser($user->id);

        self::assertCount(2, $chantiers);
        foreach ($chantiers as $chantier) {
            self::assertInstanceOf(Chantier::class, $chantier);
            self::assertTrue($user->id->equals($chantier->proprietaireId));
        }
    }

    #[Test]
    public function il_supprime_un_chantier_par_id(): void
    {
        $user = UserFactory::createOne();
        $chantier = Chantier::creer($user->id, new Adresse('4 rue', '13001', 'Marseille'));
        $this->repository->save($chantier);

        $this->repository->delete($chantier->id);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById($chantier->id));
    }

    #[Test]
    public function il_ne_leve_pas_d_erreur_si_l_id_a_supprimer_n_existe_pas(): void
    {
        $this->repository->delete(Uuid::v7());

        $this->expectNotToPerformAssertions();
    }
}
