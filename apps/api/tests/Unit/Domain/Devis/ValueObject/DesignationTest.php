<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\DevisException;
use App\Domain\Devis\ValueObject\Designation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DesignationTest extends TestCase
{
    #[Test]
    public function elle_conserve_le_libelle_sans_les_espaces_de_debut_et_de_fin(): void
    {
        self::assertSame('Pose carrelage sol', Designation::depuisChaine("  Pose carrelage sol \t")->libelle);
    }

    #[Test]
    public function elle_accepte_un_libelle_d_un_seul_caractere(): void
    {
        self::assertSame('x', Designation::depuisChaine('x')->libelle);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function designationsVides(): iterable
    {
        yield 'vide' => [''];
        yield 'espaces' => ['   '];
        yield 'tabulation et retours ligne' => ["\t\r\n"];
        yield 'espace insecable' => ["\u{00A0}"];
        yield 'espace cadratin' => ["\u{2003}"];
    }

    #[Test]
    #[DataProvider('designationsVides')]
    public function elle_refuse_un_libelle_vide_apres_suppression_des_espaces(string $chaine): void
    {
        $this->expectException(DevisException::class);

        Designation::depuisChaine($chaine);
    }
}
