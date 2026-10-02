<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\ChaineDecimaleInvalideException;
use App\Domain\Devis\ValueObject\ChaineDecimale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChaineDecimaleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function chainesValides(): iterable
    {
        yield 'entier zero' => ['0', 0, '0'];
        yield 'une decimale' => ['12.5', 1, '12.5'];
        yield 'trois decimales' => ['0.125', 3, '0.125'];
        yield 'zeros de tete' => ['007', 0, '7'];
        yield 'zeros finaux conserves' => ['1.500', 3, '1.500'];
        yield 'grand nombre' => ['123456789012345678901234567890.12', 2, '123456789012345678901234567890.12'];
    }

    #[Test]
    #[DataProvider('chainesValides')]
    public function elle_convertit_une_chaine_decimale_valide_en_nombre_exact(string $chaine, int $decimalesMax, string $attendu): void
    {
        $nombre = ChaineDecimale::versNombre($chaine, $decimalesMax);

        self::assertSame($attendu, (string) $nombre);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formatsInvalides(): iterable
    {
        yield 'vide' => [''];
        yield 'point initial' => ['.5'];
        yield 'point final' => ['5.'];
        yield 'signe moins' => ['-1'];
        yield 'signe plus' => ['+1'];
        yield 'exposant' => ['1e3'];
        yield 'virgule' => ['1,5'];
        yield 'espace initial' => [' 1'];
        yield 'espace final' => ['1 '];
        yield 'retour ligne final' => ["12\n"];
        yield 'deux points' => ['1.2.3'];
        yield 'hexadecimal' => ['0x1A'];
        yield 'chiffres non ascii' => ['١٢'];
        yield 'point seul' => ['.'];
        yield 'infini' => ['INF'];
        yield 'nan' => ['NAN'];
    }

    #[Test]
    #[DataProvider('formatsInvalides')]
    public function elle_refuse_un_format_invalide(string $chaine): void
    {
        $this->expectException(ChaineDecimaleInvalideException::class);

        ChaineDecimale::versNombre($chaine, 10);
    }

    #[Test]
    public function elle_refuse_plus_de_decimales_que_le_maximum_en_comptant_les_zeros_finaux(): void
    {
        $this->expectException(ChaineDecimaleInvalideException::class);

        ChaineDecimale::versNombre('1.500', 2);
    }

    #[Test]
    public function elle_accepte_exactement_le_nombre_maximal_de_decimales(): void
    {
        self::assertSame('1.50', (string) ChaineDecimale::versNombre('1.50', 2));
    }

    #[Test]
    public function elle_refuse_toute_decimale_quand_le_maximum_est_zero(): void
    {
        $this->expectException(ChaineDecimaleInvalideException::class);

        ChaineDecimale::versNombre('1.0', 0);
    }
}
