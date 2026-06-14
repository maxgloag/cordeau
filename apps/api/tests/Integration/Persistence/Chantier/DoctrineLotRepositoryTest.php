<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence\Chantier;

use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Enum\ModeFacturation;
use App\Domain\Chantier\Exception\LotIntrouvableException;
use App\Domain\Chantier\ValueObject\Estimation;
use App\Domain\Chantier\ValueObject\Imprevu;
use App\Domain\Chantier\ValueObject\Reel;
use App\Infrastructure\Persistence\Doctrine\Chantier\Repository\DoctrineLotRepository;
use App\Tests\Factory\ChantierFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineLotRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineLotRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $this->entityManager = $entityManager;
        $this->repository = new DoctrineLotRepository($entityManager);
    }

    private function chantierId(): Uuid
    {
        return ChantierFactory::createOne()->id;
    }

    #[Test]
    public function il_persiste_un_lot_complet_et_le_retrouve_par_id(): void
    {
        $lot = Lot::creer($this->chantierId(), 'Peinture salon', ModeFacturation::SURFACE, 1)
            ->definirEstimation(new Estimation(25.5))
            ->enregistrerReel(new Reel(27.0));

        $this->repository->save($lot);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($lot->id);

        self::assertNotNull($retrouve);
        self::assertTrue($lot->id->equals($retrouve->id));
        self::assertSame('Peinture salon', $retrouve->nom);
        self::assertSame(ModeFacturation::SURFACE, $retrouve->mode);
        self::assertSame(1, $retrouve->ordre);
        self::assertNotNull($retrouve->estimation);
        self::assertSame(25.5, $retrouve->estimation->valeur);
        self::assertNotNull($retrouve->reel);
        self::assertSame(27.0, $retrouve->reel->valeur);
    }

    #[Test]
    public function il_persiste_un_lot_sans_estimation_ni_reel(): void
    {
        $lot = Lot::creer($this->chantierId(), 'Dépose', ModeFacturation::FORFAIT);

        $this->repository->save($lot);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($lot->id);

        self::assertNotNull($retrouve);
        self::assertNull($retrouve->estimation);
        self::assertNull($retrouve->reel);
        self::assertSame([], $retrouve->imprevus);
    }

    #[Test]
    public function il_persiste_et_restaure_les_imprevus(): void
    {
        $horodatage = new \DateTimeImmutable('2026-06-14 09:30:00');
        $lot = Lot::creer($this->chantierId(), 'Carrelage', ModeFacturation::SURFACE)
            ->ajouterImprevu(new Imprevu('Sol non plan', $horodatage));

        $this->repository->save($lot);
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($lot->id);

        self::assertNotNull($retrouve);
        self::assertCount(1, $retrouve->imprevus);
        self::assertSame('Sol non plan', $retrouve->imprevus[0]->note);
        self::assertEquals($horodatage, $retrouve->imprevus[0]->horodatage);
    }

    #[Test]
    public function il_retourne_null_si_l_id_n_existe_pas(): void
    {
        self::assertNull($this->repository->findById(Uuid::v7()));
    }

    #[Test]
    public function get_by_id_leve_une_exception_si_absent(): void
    {
        self::expectException(LotIntrouvableException::class);
        $this->repository->getById(Uuid::v7());
    }

    #[Test]
    public function il_met_a_jour_un_lot_existant_lors_du_save(): void
    {
        $lot = Lot::creer($this->chantierId(), 'Ancien', ModeFacturation::TEMPS);
        $this->repository->save($lot);

        $this->repository->save($lot->renommer('Nouveau')->definirEstimation(new Estimation(10.0)));
        $this->entityManager->clear();

        $retrouve = $this->repository->findById($lot->id);

        self::assertNotNull($retrouve);
        self::assertSame('Nouveau', $retrouve->nom);
        self::assertNotNull($retrouve->estimation);
        self::assertSame(10.0, $retrouve->estimation->valeur);
    }

    #[Test]
    public function il_liste_les_lots_d_un_chantier_tries_par_ordre(): void
    {
        $chantierId = $this->chantierId();
        $autreChantierId = $this->chantierId();

        $this->repository->save(Lot::creer($chantierId, 'Second', ModeFacturation::TEMPS, 2));
        $this->repository->save(Lot::creer($chantierId, 'Premier', ModeFacturation::TEMPS, 1));
        $this->repository->save(Lot::creer($autreChantierId, 'Autre', ModeFacturation::TEMPS, 1));

        $lots = $this->repository->findAllForChantier($chantierId);

        self::assertCount(2, $lots);
        self::assertSame('Premier', $lots[0]->nom);
        self::assertSame('Second', $lots[1]->nom);
    }

    #[Test]
    public function il_supprime_un_lot_par_id(): void
    {
        $lot = Lot::creer($this->chantierId(), 'À supprimer', ModeFacturation::TEMPS);
        $this->repository->save($lot);

        $this->repository->delete($lot->id);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById($lot->id));
    }
}
