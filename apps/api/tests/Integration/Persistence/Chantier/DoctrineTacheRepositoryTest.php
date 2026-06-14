<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence\Chantier;

use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\TacheIntrouvableException;
use App\Infrastructure\Persistence\Doctrine\Chantier\Repository\DoctrineTacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineTacheRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineTacheRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $this->entityManager = $entityManager;
        $this->repository = new DoctrineTacheRepository($entityManager);
    }

    #[Test]
    public function il_persiste_une_tache_non_faite_et_la_retrouve(): void
    {
        $lotId = Uuid::v7();
        $tache = Tache::creer($lotId, 'Poncer', 3);

        $this->repository->save($tache);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($tache->id);

        self::assertNotNull($retrouve);
        self::assertTrue($lotId->equals($retrouve->lotId));
        self::assertSame('Poncer', $retrouve->libelle);
        self::assertSame(3, $retrouve->ordre);
        self::assertFalse($retrouve->faite);
        self::assertNull($retrouve->faiteLe);
    }

    #[Test]
    public function il_persiste_une_tache_cochee_avec_son_horodatage(): void
    {
        $faiteLe = new \DateTimeImmutable('2026-06-14 16:45:00');
        $tache = Tache::creer(Uuid::v7(), 'Nettoyer')->cocher($faiteLe);

        $this->repository->save($tache);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($tache->id);

        self::assertNotNull($retrouve);
        self::assertTrue($retrouve->faite);
        self::assertEquals($faiteLe, $retrouve->faiteLe);
    }

    #[Test]
    public function il_retourne_null_si_l_id_n_existe_pas(): void
    {
        self::assertNull($this->repository->findById(Uuid::v7()));
    }

    #[Test]
    public function get_by_id_leve_une_exception_si_absent(): void
    {
        self::expectException(TacheIntrouvableException::class);
        $this->repository->getById(Uuid::v7());
    }

    #[Test]
    public function il_met_a_jour_une_tache_existante_lors_du_save(): void
    {
        $tache = Tache::creer(Uuid::v7(), 'Tâche');
        $this->repository->save($tache);

        $this->repository->save($tache->cocher(new \DateTimeImmutable('2026-06-14 17:00:00')));
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($tache->id);

        self::assertNotNull($retrouve);
        self::assertTrue($retrouve->faite);
    }

    #[Test]
    public function il_liste_les_taches_d_un_lot_triees_par_ordre(): void
    {
        $lotId = Uuid::v7();
        $autreLotId = Uuid::v7();

        $this->repository->save(Tache::creer($lotId, 'Seconde', 2));
        $this->repository->save(Tache::creer($lotId, 'Première', 1));
        $this->repository->save(Tache::creer($autreLotId, 'Autre', 1));

        $taches = $this->repository->findAllForLot($lotId);

        self::assertCount(2, $taches);
        self::assertSame('Première', $taches[0]->libelle);
        self::assertSame('Seconde', $taches[1]->libelle);
    }

    #[Test]
    public function il_supprime_une_tache_par_id(): void
    {
        $tache = Tache::creer(Uuid::v7(), 'À supprimer');
        $this->repository->save($tache);

        $this->repository->delete($tache->id);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById($tache->id));
    }
}
