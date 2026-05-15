<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Chantier;

use App\Domain\Chantier\Enum\StatutChantier;
use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\Api\JsonTestHelper;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ChantierApiTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    #[Test]
    public function get_collection_retourne_un_tableau_vide(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->request('GET', '/api/chantiers', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        self::assertCount(0, self::decodeJson($response));
    }

    #[Test]
    public function get_collection_retourne_seulement_les_chantiers_du_user(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $client->loginUser($user->_real());

        ChantierFactory::createMany(2, ['proprietaire' => $user]);
        ChantierFactory::createOne(['proprietaire' => $autreUser]);

        $client->request('GET', '/api/chantiers', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $items = json_decode($response, true);
        \assert(\is_array($items) && \count($items) === 2);
        self::assertCount(2, $items);
    }

    #[Test]
    public function get_collection_sans_auth_retourne_401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/chantiers', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function post_cree_un_chantier_et_retourne_201(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'adresseRue' => '12 rue de la Paix',
                'adresseCodePostal' => '75002',
                'adresseVille' => 'Paris',
                'adressePays' => 'FR',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertSame('12 rue de la Paix', $data['adresseRue']);
        self::assertSame('en_preparation', $data['statut']);
    }

    #[Test]
    public function post_avec_id_fourni_cree_avec_cet_id(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $uuidLocal = '0192f5e0-aaaa-7890-abcd-ef0123456789';

        $client->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'uuid' => $uuidLocal,
                'adresseRue' => '12 rue de la Paix',
                'adresseCodePostal' => '75002',
                'adresseVille' => 'Paris',
                'adressePays' => 'FR',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        self::assertSame($uuidLocal, self::decodeJson($response)['id']);
    }

    #[Test]
    public function post_avec_id_deja_existant_retourne_409(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $existant = \App\Tests\Factory\ChantierFactory::createOne(['proprietaire' => $user]);

        $client->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'uuid' => $existant->id->toRfc4122(),
                'adresseRue' => '99 avenue Test',
                'adresseCodePostal' => '75003',
                'adresseVille' => 'Paris',
                'adressePays' => 'FR',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function post_avec_id_et_clientId_fournis_cree_avec_les_deux(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $clientExistant = \App\Tests\Factory\ClientFactory::createOne(['proprietaire' => $user]);

        $uuidChantier = '0192f5e0-bbbb-7890-abcd-ef0123456789';

        $httpClient->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'uuid' => $uuidChantier,
                'adresseRue' => '12 rue de la Paix',
                'adresseCodePostal' => '75002',
                'adresseVille' => 'Paris',
                'adressePays' => 'FR',
                'clientId' => $clientExistant->id->toRfc4122(),
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $response = $httpClient->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertSame($uuidChantier, $data['id']);
        self::assertSame($clientExistant->id->toRfc4122(), $data['clientId']);
    }

    #[Test]
    public function post_avec_donnees_invalides_retourne_422(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['adresseRue' => '', 'adresseCodePostal' => '75002', 'adresseVille' => 'Paris']) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function get_item_retourne_le_chantier(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $entite = ChantierFactory::createOne(['proprietaire' => $user]);

        $client->request('GET', '/api/chantiers/' . $entite->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        self::assertSame($entite->id->toRfc4122(), self::decodeJson($response)['id']);
    }

    #[Test]
    public function get_item_retourne_404_si_id_inconnu(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());
        $client->request('GET', '/api/chantiers/00000000-0000-7000-8000-000000000001', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function patch_modifie_le_chantier(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $entite = ChantierFactory::createOne(['proprietaire' => $user, 'adresseRue' => '1 vieille rue', 'adresseCodePostal' => '75001', 'adresseVille' => 'Paris', 'adressePays' => 'FR']);

        $client->request(
            'PATCH',
            '/api/chantiers/' . $entite->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['adresseRue' => '99 nouvelle rue']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        self::assertSame('99 nouvelle rue', self::decodeJson($response)['adresseRue']);
    }

    #[Test]
    public function patch_avec_id_inconnu_retourne_404(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request(
            'PATCH',
            '/api/chantiers/00000000-0000-7000-8000-000000000001',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['surfaceM2' => 50.0]) ?: '',
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function delete_archive_le_chantier(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $entite = ChantierFactory::createOne(['proprietaire' => $user]);

        $client->request('DELETE', '/api/chantiers/' . $entite->id->toRfc4122());
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/chantiers/' . $entite->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        self::assertSame('archive', self::decodeJson($response)['statut']);
    }

    #[Test]
    public function delete_retourne_404_si_id_inconnu(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());
        $client->request('DELETE', '/api/chantiers/00000000-0000-7000-8000-000000000001');
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function get_collection_exclut_les_chantiers_archives(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        ChantierFactory::createOne(['proprietaire' => $user, 'statut' => StatutChantier::EN_COURS]);
        ChantierFactory::createOne(['proprietaire' => $user, 'statut' => StatutChantier::ARCHIVE]);

        $client->request('GET', '/api/chantiers', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $items = json_decode($response, true);
        \assert(\is_array($items));
        self::assertCount(1, $items);
    }
}
