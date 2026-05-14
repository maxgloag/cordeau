<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Client;

use App\Tests\Factory\ClientFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\Api\JsonTestHelper;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ClientApiTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    #[Test]
    public function get_collection_retourne_tableau_vide(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', '/api/clients', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        self::assertCount(0, self::decodeJson($response));
    }

    #[Test]
    public function get_collection_retourne_seulement_les_clients_du_user(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $httpClient->loginUser($user->_real());

        ClientFactory::createMany(3, ['proprietaire' => $user]);
        ClientFactory::createOne(['proprietaire' => $autreUser]);

        $httpClient->request('GET', '/api/clients', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $response = $httpClient->getResponse()->getContent();
        \assert($response !== false);
        self::assertCount(3, self::decodeJson($response));
    }

    #[Test]
    public function get_collection_sans_auth_retourne_401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/clients', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function post_cree_un_client_et_retourne_201(): void
    {
        $httpClient = static::createClient();
        $httpClient->loginUser(UserFactory::createOne()->_real());

        $httpClient->request(
            'POST',
            '/api/clients',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'nom' => 'ACME SARL',
                'email' => 'contact@acme.fr',
                'telephone' => '06 12 34 56 78',
                'adresseRue' => '10 rue de la Paix',
                'adresseCodePostal' => '75002',
                'adresseVille' => 'Paris',
                'adressePays' => 'FR',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $response = $httpClient->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertSame('ACME SARL', $data['nom']);
        self::assertSame('+33612345678', $data['telephone']);
    }

    #[Test]
    public function post_sans_nom_retourne_422(): void
    {
        $httpClient = static::createClient();
        $httpClient->loginUser(UserFactory::createOne()->_real());

        $httpClient->request(
            'POST',
            '/api/clients',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'nom' => '',
                'adresseRue' => '1 rue Test',
                'adresseCodePostal' => '75001',
                'adresseVille' => 'Paris',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function post_sans_auth_retourne_401(): void
    {
        $httpClient = static::createClient();
        $httpClient->request('POST', '/api/clients', server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function get_item_retourne_le_client(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $entite = ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'Test Client']);

        $httpClient->request('GET', '/api/clients/' . $entite->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $response = $httpClient->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertSame($entite->id->toRfc4122(), $data['id']);
        self::assertSame('Test Client', $data['nom']);
    }

    #[Test]
    public function get_item_retourne_404_si_id_inconnu(): void
    {
        $httpClient = static::createClient();
        $httpClient->loginUser(UserFactory::createOne()->_real());
        $httpClient->request('GET', '/api/clients/00000000-0000-7000-8000-000000000001', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function get_item_retourne_403_si_client_appartient_a_un_autre_user(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $entite = ClientFactory::createOne(['proprietaire' => $autreUser]);

        $httpClient->request('GET', '/api/clients/' . $entite->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function patch_modifie_le_client(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $entite = ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'Ancien nom']);

        $httpClient->request(
            'PATCH',
            '/api/clients/' . $entite->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['nom' => 'Nouveau nom']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $response = $httpClient->getResponse()->getContent();
        \assert($response !== false);
        self::assertSame('Nouveau nom', self::decodeJson($response)['nom']);
    }

    #[Test]
    public function patch_retourne_403_sur_client_d_un_autre_user(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $entite = ClientFactory::createOne(['proprietaire' => $autreUser]);

        $httpClient->request(
            'PATCH',
            '/api/clients/' . $entite->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['nom' => 'Hack']) ?: '',
        );

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function delete_supprime_le_client_et_retourne_204(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $entite = ClientFactory::createOne(['proprietaire' => $user]);
        $id = $entite->id->toRfc4122();

        $httpClient->request('DELETE', '/api/clients/' . $id);
        self::assertResponseStatusCodeSame(204);

        $httpClient->request('GET', '/api/clients/' . $id, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function delete_retourne_403_sur_client_d_un_autre_user(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $entite = ClientFactory::createOne(['proprietaire' => $autreUser]);

        $httpClient->request('DELETE', '/api/clients/' . $entite->id->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function delete_retourne_404_si_id_inconnu(): void
    {
        $httpClient = static::createClient();
        $httpClient->loginUser(UserFactory::createOne()->_real());
        $httpClient->request('DELETE', '/api/clients/00000000-0000-7000-8000-000000000001');
        self::assertResponseStatusCodeSame(404);
    }
}
