<?php

declare(strict_types=1);

namespace App\Tests\Unit\Conformite;

use App\Auth\ReglesInscription;
use App\Domain\Chantier\Exception\SurfaceInvalideException;
use App\Presentation\Api\Chantier\Payload\CreerChantierPayload;
use App\Presentation\Api\Chantier\Payload\CreerLotPayload;
use App\Presentation\Api\Chantier\Payload\CreerTachePayload;
use App\Presentation\Api\Client\Payload\CreerClientPayload;
use App\Presentation\Api\Photo\Payload\ModifierLegendePayload;
use App\Shared\Exception\AdresseInvalideException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Runner des fixtures de conformité partagées avec le web et le mobile
 * (packages/shared/conformite/README.md). L'API est la référence : chaque cas
 * passe par les contraintes du payload puis par les value objects, sans base.
 */
final class ConformiteFixturesTest extends TestCase
{
    private const string DOSSIER = __DIR__ . '/../../../../../packages/shared/conformite';

    private static ?ValidatorInterface $validator = null;

    /** @return iterable<string, array{string, array<string, mixed>, bool}> */
    public static function cas(): iterable
    {
        $fichiers = glob(self::DOSSIER . '/*.json');
        self::assertNotFalse($fichiers);
        self::assertNotEmpty($fichiers, 'Aucune fixture de conformité trouvée.');

        foreach ($fichiers as $fichier) {
            $contenu = file_get_contents($fichier);
            self::assertIsString($contenu);
            $fixture = json_decode($contenu, true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($fixture);
            self::assertIsString($fixture['regle']);
            self::assertIsArray($fixture['cas']);

            foreach ($fixture['cas'] as $cas) {
                self::assertIsArray($cas);
                self::assertIsString($cas['nom']);
                self::assertIsArray($cas['entree']);
                self::assertIsBool($cas['valide']);
                /** @var array<string, mixed> $entree */
                $entree = $cas['entree'];

                yield $fixture['regle'] . ' : ' . $cas['nom'] => [$fixture['regle'], $entree, $cas['valide']];
            }
        }
    }

    /** @param array<string, mixed> $entree */
    #[Test]
    #[DataProvider('cas')]
    public function l_api_rend_le_verdict_attendu(string $regle, array $entree, bool $valide): void
    {
        self::assertSame($valide, $this->evaluer($regle, $entree));
    }

    /** @param array<string, mixed> $entree */
    private function evaluer(string $regle, array $entree): bool
    {
        return match ($regle) {
            'adresse.rue' => $this->chantierValide(adresseRue: self::chaine($entree, 'rue')),
            'adresse.ville' => $this->chantierValide(adresseVille: self::chaine($entree, 'ville')),
            'adresse.codePostal' => $this->chantierValide(
                adresseCodePostal: self::chaine($entree, 'codePostal'),
                adressePays: self::chaine($entree, 'pays'),
            ),
            'adresse.pays' => $this->chantierValide(adressePays: self::chaine($entree, 'pays')),
            'chantier.surface' => $this->chantierValide(surfaceM2: self::nombre($entree, 'surfaceM2')),
            'client.nom' => $this->sansViolation(new CreerClientPayload(
                nom: self::chaine($entree, 'nom'),
                adresseRue: '1 rue de la Paix',
                adresseCodePostal: '75002',
                adresseVille: 'Paris',
            )),
            'client.email' => $this->sansViolation(new CreerClientPayload(
                nom: 'Dupont',
                email: self::chaine($entree, 'email'),
                adresseRue: '1 rue de la Paix',
                adresseCodePostal: '75002',
                adresseVille: 'Paris',
            )),
            'auth.email' => \count($this->validator()->validate(
                ReglesInscription::normaliserEmail(self::chaine($entree, 'email')),
                ReglesInscription::contraintesEmail(),
            )) === 0,
            'auth.motDePasse' => \count($this->validator()->validate(
                self::chaine($entree, 'motDePasse'),
                ReglesInscription::contraintesMotDePasse(),
            )) === 0,
            'photo.legende' => $this->sansViolation(new ModifierLegendePayload(self::chaine($entree, 'legende'))),
            'lot.nom' => $this->sansViolation(new CreerLotPayload(nom: self::chaine($entree, 'nom'), mode: 'temps')),
            'lot.estimation' => $this->sansViolation(new CreerLotPayload(
                nom: 'Maçonnerie',
                mode: 'temps',
                estimation: self::nombre($entree, 'estimation'),
            )),
            'tache.libelle' => $this->sansViolation(new CreerTachePayload(libelle: self::chaine($entree, 'libelle'))),
            default => self::fail(\sprintf('Règle « %s » sans implémentation dans le runner API.', $regle)),
        };
    }

    private function chantierValide(
        string $adresseRue = '1 rue de la Paix',
        string $adresseCodePostal = '75002',
        string $adresseVille = 'Paris',
        string $adressePays = 'FR',
        ?float $surfaceM2 = null,
    ): bool {
        $payload = new CreerChantierPayload(
            adresseRue: $adresseRue,
            adresseCodePostal: $adresseCodePostal,
            adresseVille: $adresseVille,
            adressePays: $adressePays,
            surfaceM2: $surfaceM2,
        );

        if (!$this->sansViolation($payload)) {
            return false;
        }

        try {
            $payload->toAdresse();
            $payload->toSurface();
        } catch (AdresseInvalideException|SurfaceInvalideException) {
            return false;
        }

        return true;
    }

    private function sansViolation(object $payload): bool
    {
        return \count($this->validator()->validate($payload)) === 0;
    }

    private function validator(): ValidatorInterface
    {
        return self::$validator ??= Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    /** @param array<string, mixed> $entree */
    private static function chaine(array $entree, string $cle): string
    {
        $valeur = $entree[$cle] ?? null;
        self::assertIsString($valeur, \sprintf('Entrée « %s » attendue en chaîne.', $cle));

        return $valeur;
    }

    /** @param array<string, mixed> $entree */
    private static function nombre(array $entree, string $cle): float
    {
        $valeur = $entree[$cle] ?? null;
        self::assertTrue(\is_int($valeur) || \is_float($valeur), \sprintf('Entrée « %s » attendue en nombre.', $cle));

        return (float) $valeur;
    }
}
