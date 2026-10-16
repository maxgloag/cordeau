<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\LotFactory;
use App\Tests\Factory\PhotoFactory;
use App\Tests\Factory\TacheFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Isolation des données entre artisans : un artisan B ne lit, ne modifie et ne supprime rien
 * de ce qui appartient à l'artisan A, et la réponse ne divulgue aucune donnée de A.
 *
 * Complète les tests d'isolation déjà présents (clients : ClientApiTest ; photos : prepare / confirm /
 * patch dans PhotoApiTest ; liste des chantiers : ChantierApiTest). Issu du spike #149. Les lots et
 * les tâches (#101) héritent du périmètre de leur chantier.
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
    private const SECRET_LOT = 'Lot-secret-de-A';
    private const SECRET_TACHE = 'Tache-secrete-de-A';

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

    #[Test]
    public function lister_les_lots_du_chantier_d_un_autre_artisan_est_refuse_sans_fuite(): void
    {
        $client = static::createClient();
        [, $chantierId] = self::lotDeA();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', '/api/chantiers/' . $chantierId->toRfc4122() . '/lots', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertRefusSansFuite($client);
    }

    #[Test]
    public function creer_un_lot_sur_le_chantier_d_un_autre_artisan_est_refuse_et_ne_cree_rien(): void
    {
        $client = static::createClient();
        $chantier = ChantierFactory::createOne(['proprietaire' => UserFactory::createOne()]);
        $client->loginUser(UserFactory::createOne()->_real());

        self::postJson($client, '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', ['nom' => 'Lot pirate', 'mode' => 'forfait']);

        self::assertRefusSansFuite($client);
        LotFactory::assert()->count(0);
    }

    #[Test]
    public function creer_un_lot_avec_l_uuid_du_lot_d_un_autre_artisan_ne_le_detourne_pas(): void
    {
        $client = static::createClient();
        [$lotId, $chantierDeA] = self::lotDeA();
        $pirate = UserFactory::createOne();
        $chantierDuPirate = ChantierFactory::createOne(['proprietaire' => $pirate]);
        $client->loginUser($pirate->_real());

        self::postJson($client, '/api/chantiers/' . $chantierDuPirate->id->toRfc4122() . '/lots', ['nom' => 'Lot pirate', 'mode' => 'forfait', 'uuid' => $lotId->toRfc4122()]);

        self::assertResponseStatusCodeSame(409);
        self::assertStringNotContainsString(self::SECRET_LOT, (string) $client->getResponse()->getContent());
        LotFactory::assert()->exists(['id' => $lotId, 'chantierId' => $chantierDeA, 'nom' => self::SECRET_LOT]);
    }

    #[Test]
    public function get_lot_d_un_autre_artisan_est_refuse_sans_fuite(): void
    {
        $client = static::createClient();
        [$lotId] = self::lotDeA();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', '/api/lots/' . $lotId->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertRefusSansFuite($client);
    }

    #[Test]
    public function patch_lot_d_un_autre_artisan_est_refuse_et_ne_modifie_rien(): void
    {
        $client = static::createClient();
        [$lotId] = self::lotDeA();
        $client->loginUser(UserFactory::createOne()->_real());

        self::patchJson($client, '/api/lots/' . $lotId->toRfc4122(), ['nom' => 'Pirate']);

        self::assertRefusSansFuite($client);
        LotFactory::assert()->exists(['id' => $lotId, 'nom' => self::SECRET_LOT]);
    }

    #[Test]
    public function delete_lot_d_un_autre_artisan_est_refuse_et_ne_supprime_rien(): void
    {
        $client = static::createClient();
        [$lotId] = self::lotDeA();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('DELETE', '/api/lots/' . $lotId->toRfc4122());

        self::assertRefusSansFuite($client);
        LotFactory::assert()->exists(['id' => $lotId]);
        TacheFactory::assert()->count(1, ['lotId' => $lotId]);
    }

    #[Test]
    public function lister_les_taches_du_lot_d_un_autre_artisan_est_refuse_sans_fuite(): void
    {
        $client = static::createClient();
        [$lotId] = self::lotDeA();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', '/api/lots/' . $lotId->toRfc4122() . '/taches', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertRefusSansFuite($client);
    }

    #[Test]
    public function creer_une_tache_dans_le_lot_d_un_autre_artisan_est_refuse_et_ne_cree_rien(): void
    {
        $client = static::createClient();
        [$lotId] = self::lotDeA();
        $client->loginUser(UserFactory::createOne()->_real());

        self::postJson($client, '/api/lots/' . $lotId->toRfc4122() . '/taches', ['libelle' => 'Tache pirate']);

        self::assertRefusSansFuite($client);
        TacheFactory::assert()->count(1, ['lotId' => $lotId]);
    }

    #[Test]
    public function patch_tache_d_un_autre_artisan_est_refuse_et_ne_modifie_rien(): void
    {
        $client = static::createClient();
        [$lotId] = self::lotDeA();
        $tache = TacheFactory::find(['lotId' => $lotId]);
        $client->loginUser(UserFactory::createOne()->_real());

        self::patchJson($client, '/api/taches/' . $tache->id->toRfc4122(), ['faite' => true, 'libelle' => 'Pirate']);

        self::assertRefusSansFuite($client);
        TacheFactory::assert()->exists(['id' => $tache->id, 'libelle' => self::SECRET_TACHE, 'faite' => false]);
    }

    #[Test]
    public function delete_tache_d_un_autre_artisan_est_refuse_et_ne_supprime_rien(): void
    {
        $client = static::createClient();
        [$lotId] = self::lotDeA();
        $tache = TacheFactory::find(['lotId' => $lotId]);
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('DELETE', '/api/taches/' . $tache->id->toRfc4122());

        self::assertRefusSansFuite($client);
        TacheFactory::assert()->exists(['id' => $tache->id]);
    }

    /**
     * Un lot de l'artisan A, avec une tâche, sur un chantier de A.
     *
     * @return array{Uuid, Uuid} identifiants du lot et du chantier
     */
    private static function lotDeA(): array
    {
        $chantier = ChantierFactory::createOne(['proprietaire' => UserFactory::createOne(), 'adresseRue' => self::SECRET_RUE]);
        $lot = LotFactory::createOne(['chantierId' => $chantier->id, 'nom' => self::SECRET_LOT]);
        TacheFactory::createOne(['lotId' => $lot->id, 'libelle' => self::SECRET_TACHE]);

        return [$lot->id, $chantier->id];
    }

    private static function assertRefusSansFuite(KernelBrowser $client): void
    {
        $contenu = (string) $client->getResponse()->getContent();

        self::assertContains($client->getResponse()->getStatusCode(), [403, 404], 'Accès à la ressource d\'un autre artisan non refusé : ' . $client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(self::SECRET_RUE, $contenu);
        self::assertStringNotContainsString(self::SECRET_LEGENDE, $contenu);
        self::assertStringNotContainsString(self::SECRET_LOT, $contenu);
        self::assertStringNotContainsString(self::SECRET_TACHE, $contenu);
    }
}
