<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Chantier;

use App\Domain\Chantier\Enum\StatutChantier;
use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ChantierApiTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(string $content): array
    {
        $decoded = json_decode($content, true);
        \assert(\is_array($decoded));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

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
