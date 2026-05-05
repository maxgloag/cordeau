<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Chantier\ValueObject;

use App\Domain\Chantier\Exception\AdresseInvalideException;
use App\Domain\Chantier\ValueObject\Adresse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdresseTest extends TestCase
{
    #[Test]
    public function elle_se_construit_avec_des_valeurs_valides(): void
    {
        $adresse = new Adresse(
            rue: '12 rue de la Paix',
            codePostal: '75002',
            ville: 'Paris',
            pays: 'FR',
        );

        self::assertSame('12 rue de la Paix', $adresse->rue);
        self::assertSame('75002', $adresse->codePostal);
        self::assertSame('Paris', $adresse->ville);
        self::assertSame('FR', $adresse->pays);
    }

    #[Test]
    public function elle_utilise_FR_comme_pays_par_defaut(): void
    {
        $adresse = new Adresse('1 avenue', '75001', 'Paris');

        self::assertSame('FR', $adresse->pays);
    }

    #[Test]
    public function elle_refuse_une_rue_vide(): void
    {
        self::expectException(AdresseInvalideException::class);
        new Adresse(rue: '   ', codePostal: '75001', ville: 'Paris');
    }

    #[Test]
    public function elle_refuse_une_ville_vide(): void
    {
        self::expectException(AdresseInvalideException::class);
        new Adresse(rue: '1 rue', codePostal: '75001', ville: ' ');
    }

    #[Test]
    public function elle_refuse_un_code_postal_francais_non_a_5_chiffres(): void
    {
        self::expectException(AdresseInvalideException::class);
        new Adresse(rue: '1 rue', codePostal: '7501', ville: 'Paris', pays: 'FR');
    }

    #[Test]
    public function elle_refuse_un_code_postal_avec_des_lettres_en_FR(): void
    {
        self::expectException(AdresseInvalideException::class);
        new Adresse(rue: '1 rue', codePostal: '75A02', ville: 'Paris', pays: 'FR');
    }

    #[Test]
    public function elle_accepte_un_code_postal_alphanumerique_hors_FR(): void
    {
        $adresse = new Adresse(
            rue: '10 Downing Street',
            codePostal: 'SW1A 2AA',
            ville: 'London',
            pays: 'GB',
        );

        self::assertSame('SW1A 2AA', $adresse->codePostal);
    }

    #[Test]
    public function elle_refuse_un_code_pays_invalide(): void
    {
        self::expectException(AdresseInvalideException::class);
        new Adresse(rue: '1 rue', codePostal: '75001', ville: 'Paris', pays: 'fra');
    }

    #[Test]
    public function elle_egale_une_adresse_avec_les_memes_valeurs(): void
    {
        $a = new Adresse('1 rue', '75001', 'Paris');
        $b = new Adresse('1 rue', '75001', 'Paris');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function elle_n_egale_pas_une_adresse_differente(): void
    {
        $a = new Adresse('1 rue', '75001', 'Paris');
        $b = new Adresse('2 rue', '75001', 'Paris');

        self::assertFalse($a->equals($b));
    }
}
