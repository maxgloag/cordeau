<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\Entity;

use App\Domain\Chantier\Entity\Tache;
use App\Domain\Chantier\Exception\TacheInvalideException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class TacheTest extends TestCase
{
    #[Test]
    public function elle_se_cree_non_faite_avec_un_libelle_et_un_ordre(): void
    {
        $lotId = new UuidV7();
        $tache = Tache::creer($lotId, 'Poncer les murs', 1);

        self::assertInstanceOf(UuidV7::class, $tache->id);
        self::assertSame($lotId, $tache->lotId);
        self::assertSame('Poncer les murs', $tache->libelle);
        self::assertSame(1, $tache->ordre);
        self::assertFalse($tache->faite);
        self::assertNull($tache->faiteLe);
    }

    #[Test]
    public function elle_accepte_un_id_fourni_pour_l_offline(): void
    {
        $id = new UuidV7();
        $tache = Tache::creer(new UuidV7(), 'Tâche', 0, $id);

        self::assertSame($id, $tache->id);
    }

    #[Test]
    public function elle_refuse_un_libelle_vide_a_la_creation(): void
    {
        self::expectException(TacheInvalideException::class);
        Tache::creer(new UuidV7(), '   ');
    }

    #[Test]
    public function la_cocher_la_marque_faite_avec_horodatage_sans_muter_l_origine(): void
    {
        $maintenant = new \DateTimeImmutable('2026-06-14 14:00:00');
        $initiale = Tache::creer(new UuidV7(), 'Tâche');
        $faite = $initiale->cocher($maintenant);

        self::assertTrue($faite->faite);
        self::assertEquals($maintenant, $faite->faiteLe);
        self::assertFalse($initiale->faite, 'L\'instance d\'origine reste inchangée');
        self::assertSame($initiale->id, $faite->id);
    }

    #[Test]
    public function la_decocher_efface_l_etat_fait(): void
    {
        $tache = Tache::creer(new UuidV7(), 'Tâche')
            ->cocher(new \DateTimeImmutable())
            ->decocher();

        self::assertFalse($tache->faite);
        self::assertNull($tache->faiteLe);
    }

    #[Test]
    public function la_renommer_change_le_libelle_sans_muter_l_origine(): void
    {
        $initiale = Tache::creer(new UuidV7(), 'Ancien');
        $modifiee = $initiale->renommer('Nouveau');

        self::assertSame('Nouveau', $modifiee->libelle);
        self::assertSame('Ancien', $initiale->libelle);
    }

    #[Test]
    public function la_renommer_refuse_un_libelle_vide(): void
    {
        $tache = Tache::creer(new UuidV7(), 'Valide');

        self::expectException(TacheInvalideException::class);
        $tache->renommer('');
    }
}
