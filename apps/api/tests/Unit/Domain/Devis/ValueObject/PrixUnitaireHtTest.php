<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Devis\ValueObject;

use App\Domain\Devis\Exception\DevisException;
use App\Domain\Devis\ValueObject\PrixUnitaireHt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrixUnitaireHtTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prixValides(): iterable
    {
        yield 'zero' => ['0', '0'];
        yield 'deux decimales' => ['45.90', '45.90'];
        yield 'borne superieure' => ['10000000', '10000000'];
        yield 'borne superieure avec decimales nulles' => ['10000000.00', '10000000.00'];
    }

    #[Test]
    #[DataProvider('prixValides')]
    public function il_accepte_un_prix_valide(string $chaine, string $attendu): void
    {
        self::assertSame($attendu, (string) PrixUnitaireHt::depuisChaine($chaine)->valeur);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function prixInvalides(): iterable
    {
        yield 'au dessus de la borne de peu' => ['10000000.01'];
        yield 'au dessus de la borne' => ['10000001'];
        yield 'trois decimales' => ['1.001'];
        yield 'trois decimales nulles' => ['1.000'];
        yield 'format invalide' => ['1e3'];
        yield 'negatif' => ['-0.01'];
    }

    #[Test]
    #[DataProvider('prixInvalides')]
    public function il_refuse_un_prix_invalide(string $chaine): void
    {
        $this->expectException(DevisException::class);

        PrixUnitaireHt::depuisChaine($chaine);
    }
}
