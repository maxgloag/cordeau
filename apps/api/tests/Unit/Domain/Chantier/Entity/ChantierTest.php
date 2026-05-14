<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\Entity;

use App\Domain\Chantier\Entity\Chantier;
use App\Domain\Chantier\Enum\StatutChantier;
use App\Domain\Chantier\Exception\TransitionStatutInvalideException;
use App\Shared\ValueObject\Adresse;
use App\Domain\Chantier\ValueObject\Surface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class ChantierTest extends TestCase
{
    #[Test]
    public function il_se_cree_avec_le_statut_en_preparation(): void
    {
        $chantier = Chantier::creer(new UuidV7(), self::adresseValide());

        self::assertSame(StatutChantier::EN_PREPARATION, $chantier->statut);
    }

    #[Test]
    public function il_genere_un_uuid_v7_a_la_creation(): void
    {
        $chantier = Chantier::creer(new UuidV7(), self::adresseValide());

        self::assertInstanceOf(UuidV7::class, $chantier->id);
    }

    #[Test]
    public function il_initialise_les_deux_timestamps_a_la_meme_valeur(): void
    {
        $maintenant = new \DateTimeImmutable('2026-05-04 10:00:00');
        $chantier = Chantier::creer(new UuidV7(), self::adresseValide(), null, $maintenant);

        self::assertEquals($maintenant, $chantier->creeLe);
        self::assertEquals($maintenant, $chantier->modifieLe);
    }

    #[Test]
    public function il_peut_passer_de_en_preparation_a_en_cours(): void
    {
        $maintenant = new \DateTimeImmutable('2026-05-04 10:00:00');
        $apres = new \DateTimeImmutable('2026-05-04 11:00:00');

        $initial = Chantier::creer(new UuidV7(), self::adresseValide(), null, $maintenant);
        $modifie = $initial->passerEnCours($apres);

        self::assertSame(StatutChantier::EN_COURS, $modifie->statut);
        self::assertEquals($maintenant, $modifie->creeLe);
        self::assertEquals($apres, $modifie->modifieLe);
        self::assertSame(StatutChantier::EN_PREPARATION, $initial->statut, 'L\'instance d\'origine reste inchangée');
    }

    #[Test]
    public function il_peut_terminer_depuis_en_cours(): void
    {
        $chantier = Chantier::creer(new UuidV7(), self::adresseValide())->passerEnCours()->terminer();

        self::assertSame(StatutChantier::TERMINE, $chantier->statut);
    }

    #[Test]
    public function il_peut_archiver_depuis_n_importe_quel_statut_non_terminal(): void
    {
        $depuisPreparation = Chantier::creer(new UuidV7(), self::adresseValide())->archiver();
        self::assertSame(StatutChantier::ARCHIVE, $depuisPreparation->statut);

        $depuisCours = Chantier::creer(new UuidV7(), self::adresseValide())->passerEnCours()->archiver();
        self::assertSame(StatutChantier::ARCHIVE, $depuisCours->statut);

        $depuisTermine = Chantier::creer(new UuidV7(), self::adresseValide())->passerEnCours()->terminer()->archiver();
        self::assertSame(StatutChantier::ARCHIVE, $depuisTermine->statut);
    }

    #[Test]
    public function il_refuse_de_changer_de_statut_apres_archivage(): void
    {
        $archive = Chantier::creer(new UuidV7(), self::adresseValide())->archiver();

        self::expectException(TransitionStatutInvalideException::class);
        $archive->passerEnCours();
    }

    #[Test]
    public function il_refuse_d_archiver_un_chantier_deja_archive(): void
    {
        $archive = Chantier::creer(new UuidV7(), self::adresseValide())->archiver();

        self::expectException(TransitionStatutInvalideException::class);
        $archive->archiver();
    }

    #[Test]
    public function il_refuse_de_passer_au_meme_statut(): void
    {
        $enCours = Chantier::creer(new UuidV7(), self::adresseValide())->passerEnCours();

        self::expectException(TransitionStatutInvalideException::class);
        $enCours->passerEnCours();
    }

    #[Test]
    public function il_modifie_l_adresse_en_retournant_une_nouvelle_instance(): void
    {
        $maintenant = new \DateTimeImmutable('2026-05-04 10:00:00');
        $apres = new \DateTimeImmutable('2026-05-04 11:00:00');

        $initial = Chantier::creer(new UuidV7(), self::adresseValide(), null, $maintenant);
        $nouvelle = new Adresse('99 boulevard', '69001', 'Lyon');
        $modifie = $initial->modifierAdresse($nouvelle, $apres);

        self::assertTrue($modifie->adresse->equals($nouvelle));
        self::assertEquals($apres, $modifie->modifieLe);
        self::assertNotSame($initial, $modifie);
    }

    #[Test]
    public function il_renseigne_la_surface_en_retournant_une_nouvelle_instance(): void
    {
        $chantier = Chantier::creer(new UuidV7(), self::adresseValide());
        self::assertNull($chantier->surface);

        $modifie = $chantier->renseignerSurface(new Surface(75.5));

        self::assertNotNull($modifie->surface);
        self::assertSame(75.5, $modifie->surface->valeurM2);
    }

    private static function adresseValide(): Adresse
    {
        return new Adresse('12 rue de la Paix', '75002', 'Paris');
    }
}
