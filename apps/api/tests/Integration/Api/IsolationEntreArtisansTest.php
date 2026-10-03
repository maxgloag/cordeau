<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\PhotoFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Isolation des données entre artisans : un artisan B ne lit, ne modifie et ne supprime rien
 * de ce qui appartient à l'artisan A, et la réponse ne divulgue aucune donnée de A.
 *
 * Complète les tests d'isolation déjà présents (clients : ClientApiTest ; photos : prepare / confirm /
 * patch dans PhotoApiTest ; liste des chantiers : ChantierApiTest). Issu du spike #149.
 *
 * Pas de test de lecture d'une photo isolée : `GET /api/photos/{id}` n'est pas exposé (la route ne sert
 * qu'à la génération d'IRI) ; la lecture passe par la liste d'un chantier, testée ci-dessus.
 */
final class IsolationEntreArtisansTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    private const SECRET_RUE = '1 rue du Secret-de-A';
    private const SECRET_LEGENDE = 'Legende-secrete-de-A';

    #[Test]
    public function get_chantier_d_un_autre_artisan_est_refuse_sans_fuite(): void
    {
        $client = static::createClient();
        $chantier = ChantierFactory::createOne(['proprietaire' => UserFactory::createOne(), 'adresseRue' => self::SECRET_RUE]);
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertRefusSansFuite($client);
    }

    #[Test]
    public function patch_chantier_d_un_autre_artisan_est_refuse_et_ne_modifie_rien(): void
    {
        $client = static::createClient();
        $proprietaire = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $proprietaire, 'adresseRue' => self::SECRET_RUE]);
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request(
            'PATCH',
            '/api/chantiers/' . $chantier->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['adresseRue' => 'Pirate']) ?: '',
        );
        self::assertRefusSansFuite($client);

        $client->loginUser($proprietaire->_real());
        $client->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(self::SECRET_RUE, self::decodeJson((string) $client->getResponse()->getContent())['adresseRue']);
    }

    #[Test]
    public function delete_chantier_d_un_autre_artisan_est_refuse_et_n_archive_rien(): void
    {
        $client = static::createClient();
        $proprietaire = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $proprietaire]);
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('DELETE', '/api/chantiers/' . $chantier->id->toRfc4122());
        self::assertRefusSansFuite($client);

        $client->loginUser($proprietaire->_real());
        $client->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame('en_preparation', self::decodeJson((string) $client->getResponse()->getContent())['statut']);
    }

    #[Test]
    public function lister_les_photos_du_chantier_d_un_autre_artisan_ne_divulgue_rien(): void
    {
        $client = static::createClient();
        $proprietaire = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $proprietaire]);
        PhotoFactory::createOne([
            'proprietaire' => $proprietaire,
            'chantierId' => $chantier->id,
            'legende' => self::SECRET_LEGENDE,
        ]);
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122() . '/photos', server: ['HTTP_ACCEPT' => 'application/json']);

        // 403/404 ou liste vide : jamais les photos de A.
        self::assertStringNotContainsString(self::SECRET_LEGENDE, (string) $client->getResponse()->getContent());
        self::assertContains($client->getResponse()->getStatusCode(), [200, 403, 404]);
        if ($client->getResponse()->getStatusCode() === 200) {
            self::assertSame([], self::decodeJson((string) $client->getResponse()->getContent()));
        }
    }

    #[Test]
    public function delete_photo_d_un_autre_artisan_est_refuse_et_ne_supprime_rien(): void
    {
        $client = static::createClient();
        $proprietaire = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $proprietaire]);
        $photo = PhotoFactory::createOne([
            'proprietaire' => $proprietaire,
            'chantierId' => $chantier->id,
            'legende' => self::SECRET_LEGENDE,
        ]);
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('DELETE', '/api/photos/' . $photo->id->toRfc4122());
        self::assertRefusSansFuite($client);

        $client->loginUser($proprietaire->_real());
        $client->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122() . '/photos', server: ['HTTP_ACCEPT' => 'application/json']);
        $photos = json_decode((string) $client->getResponse()->getContent(), true);
        \assert(\is_array($photos));
        self::assertCount(1, $photos);
        \assert(\is_array($photos[0]));
        self::assertSame($photo->id->toRfc4122(), $photos[0]['id']);
    }

    private static function assertRefusSansFuite(KernelBrowser $client): void
    {
        $contenu = (string) $client->getResponse()->getContent();

        self::assertContains($client->getResponse()->getStatusCode(), [403, 404], 'Accès à la ressource d\'un autre artisan non refusé : ' . $client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(self::SECRET_RUE, $contenu);
        self::assertStringNotContainsString(self::SECRET_LEGENDE, $contenu);
    }
}
