<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\Entity;

use App\Domain\Chantier\Entity\Lot;
use App\Domain\Chantier\Enum\ModeFacturation;
use App\Domain\Chantier\Exception\LotInvalideException;
use App\Domain\Chantier\ValueObject\Estimation;
use App\Domain\Chantier\ValueObject\Imprevu;
use App\Domain\Chantier\ValueObject\Reel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class LotTest extends TestCase
{
    #[Test]
    public function il_se_cree_avec_un_nom_un_mode_et_un_ordre(): void
    {
        $chantierId = new UuidV7();
        $lot = Lot::creer($chantierId, 'Peinture salon', ModeFacturation::SURFACE, 2);

        self::assertInstanceOf(UuidV7::class, $lot->id);
        self::assertSame($chantierId, $lot->chantierId);
        self::assertSame('Peinture salon', $lot->nom);
        self::assertSame(ModeFacturation::SURFACE, $lot->mode);
        self::assertSame(2, $lot->ordre);
    }

    #[Test]
    public function il_se_cree_sans_estimation_ni_reel_ni_imprevu(): void
    {
        $lot = Lot::creer(new UuidV7(), 'Dépose', ModeFacturation::FORFAIT);

        self::assertNull($lot->estimation);
        self::assertNull($lot->reel);
        self::assertSame([], $lot->imprevus);
    }

    #[Test]
    public function il_accepte_un_id_fourni_pour_l_offline(): void
    {
        $id = new UuidV7();
        $lot = Lot::creer(new UuidV7(), 'Carrelage', ModeFacturation::SURFACE, 0, $id);

        self::assertSame($id, $lot->id);
    }

    #[Test]
    public function il_refuse_un_nom_vide_a_la_creation(): void
    {
        self::expectException(LotInvalideException::class);
        Lot::creer(new UuidV7(), '   ', ModeFacturation::TEMPS);
    }

    #[Test]
    public function il_se_renomme_sans_muter_l_instance_d_origine(): void
    {
        $initial = Lot::creer(new UuidV7(), 'Ancien nom', ModeFacturation::TEMPS);
        $modifie = $initial->renommer('Nouveau nom');

        self::assertSame('Nouveau nom', $modifie->nom);
        self::assertSame('Ancien nom', $initial->nom);
        self::assertSame($initial->id, $modifie->id);
    }

    #[Test]
    public function il_refuse_un_nom_vide_au_renommage(): void
    {
        $lot = Lot::creer(new UuidV7(), 'Valide', ModeFacturation::TEMPS);

        self::expectException(LotInvalideException::class);
        $lot->renommer('');
    }

    #[Test]
    public function il_definit_une_estimation(): void
    {
        $lot = Lot::creer(new UuidV7(), 'Peinture', ModeFacturation::SURFACE)
            ->definirEstimation(new Estimation(25.0));

        self::assertNotNull($lot->estimation);
        self::assertSame(25.0, $lot->estimation->valeur);
    }

    #[Test]
    public function il_enregistre_un_reel(): void
    {
        $lot = Lot::creer(new UuidV7(), 'Peinture', ModeFacturation::SURFACE)
            ->enregistrerReel(new Reel(27.5));

        self::assertNotNull($lot->reel);
        self::assertSame(27.5, $lot->reel->valeur);
    }

    #[Test]
    public function il_ajoute_des_imprevus_dans_l_ordre(): void
    {
        $premier = new Imprevu('Mur humide', new \DateTimeImmutable('2026-06-14 09:00:00'));
        $second = new Imprevu('Câble à dévier', new \DateTimeImmutable('2026-06-14 10:00:00'));

        $lot = Lot::creer(new UuidV7(), 'Peinture', ModeFacturation::SURFACE)
            ->ajouterImprevu($premier)
            ->ajouterImprevu($second);

        self::assertSame([$premier, $second], $lot->imprevus);
    }

    #[Test]
    public function changer_de_mode_reinitialise_estimation_et_reel(): void
    {
        $lot = Lot::creer(new UuidV7(), 'Poste', ModeFacturation::SURFACE)
            ->definirEstimation(new Estimation(30.0))
            ->enregistrerReel(new Reel(28.0));

        $modifie = $lot->changerMode(ModeFacturation::FORFAIT);

        self::assertSame(ModeFacturation::FORFAIT, $modifie->mode);
        self::assertNull($modifie->estimation, 'L\'estimation perd son sens quand l\'unité change');
        self::assertNull($modifie->reel, 'Le réel perd son sens quand l\'unité change');
    }

    #[Test]
    public function changer_pour_le_meme_mode_conserve_estimation_et_reel(): void
    {
        $lot = Lot::creer(new UuidV7(), 'Poste', ModeFacturation::SURFACE)
            ->definirEstimation(new Estimation(30.0));

        $modifie = $lot->changerMode(ModeFacturation::SURFACE);

        self::assertNotNull($modifie->estimation);
        self::assertSame(30.0, $modifie->estimation->valeur);
    }

    #[Test]
    public function son_identifiant_est_de_type_uuid(): void
    {
        $lot = Lot::creer(new UuidV7(), 'Poste', ModeFacturation::TEMPS);

        self::assertInstanceOf(Uuid::class, $lot->id);
    }
}
