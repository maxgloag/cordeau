<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Chantier;

use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\ClientFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\Api\JsonTestHelper;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LierClientTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    #[Test]
    public function post_chantier_avec_clientId_lie_le_client(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $client = ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'ACME SARL']);

        $httpClient->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'adresseRue' => '1 rue Test',
                'adresseCodePostal' => '75001',
                'adresseVille' => 'Paris',
                'clientId' => $client->id->toRfc4122(),
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertSame($client->id->toRfc4122(), $data['clientId']);
        self::assertSame('ACME SARL', $data['clientNom']);
    }

    #[Test]
    public function post_chantier_sans_clientId_ne_lie_pas_de_client(): void
    {
        $httpClient = static::createClient();
        $httpClient->loginUser(UserFactory::createOne()->_real());

        $httpClient->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'adresseRue' => '2 rue Test',
                'adresseCodePostal' => '75001',
                'adresseVille' => 'Paris',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertNull($data['clientId']);
        self::assertNull($data['clientNom']);
    }

    #[Test]
    public function patch_chantier_avec_clientId_met_a_jour_la_liaison(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());

        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $client = ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'Dupont Menuiserie']);

        $httpClient->request(
            'PATCH',
            '/api/chantiers/' . $chantier->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['clientId' => $client->id->toRfc4122()]) ?: '',
        );

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertSame($client->id->toRfc4122(), $data['clientId']);
        self::assertSame('Dupont Menuiserie', $data['clientNom']);
    }

    #[Test]
    public function le_nom_cache_se_met_a_jour_quand_le_client_est_renomme(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());

        $client = ClientFactory::createOne(['proprietaire' => $user, 'nom' => 'Ancien nom']);
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);

        // Liaison
        $httpClient->request(
            'PATCH',
            '/api/chantiers/' . $chantier->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['clientId' => $client->id->toRfc4122()]) ?: '',
        );
        self::assertResponseStatusCodeSame(200);

        // Renommage du client
        $httpClient->request(
            'PATCH',
            '/api/clients/' . $client->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['nom' => 'Nouveau nom']) ?: '',
        );
        self::assertResponseStatusCodeSame(200);

        // Vérification que le chantier voit le nouveau nom
        $httpClient->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertSame('Nouveau nom', $data['clientNom']);
    }

    #[Test]
    public function supprimer_client_deta_chantier_sans_le_supprimer(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $httpClient->loginUser($user->_real());

        $client = ClientFactory::createOne(['proprietaire' => $user]);
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);

        // Liaison
        $httpClient->request(
            'PATCH',
            '/api/chantiers/' . $chantier->id->toRfc4122(),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['clientId' => $client->id->toRfc4122()]) ?: '',
        );
        self::assertResponseStatusCodeSame(200);

        // Suppression du client
        $httpClient->request('DELETE', '/api/clients/' . $client->id->toRfc4122());
        self::assertResponseStatusCodeSame(204);

        // Le chantier existe toujours, sans client
        $httpClient->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson($httpClient->getResponse()->getContent() ?: '');
        self::assertNull($data['clientId']);
        self::assertNull($data['clientNom']);
    }

    #[Test]
    public function post_chantier_avec_clientId_appartenant_a_un_autre_user_retourne_422(): void
    {
        $httpClient = static::createClient();
        $user = UserFactory::createOne();
        $autreUser = UserFactory::createOne();
        $httpClient->loginUser($user->_real());
        $clientAutreUser = ClientFactory::createOne(['proprietaire' => $autreUser]);

        $httpClient->request(
            'POST',
            '/api/chantiers',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'adresseRue' => '3 rue Test',
                'adresseCodePostal' => '75001',
                'adresseVille' => 'Paris',
                'clientId' => $clientAutreUser->id->toRfc4122(),
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
    }
}
