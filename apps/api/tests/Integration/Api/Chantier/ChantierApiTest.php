<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Chantier;

use App\Tests\Factory\ChantierFactory;
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
        $client->request('GET', '/api/chantiers', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);

        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertCount(0, $data);
    }

    #[Test]
    public function get_collection_retourne_les_chantiers_existants(): void
    {
        $client = static::createClient();
        ChantierFactory::createMany(2);

        $client->request('GET', '/api/chantiers', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);

        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $items = json_decode($response, true);
        \assert(\is_array($items) && \count($items) === 2);
        $first = $items[0];
        \assert(\is_array($first));
        self::assertCount(2, $items);
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('statut', $first);
    }

    #[Test]
    public function post_cree_un_chantier_et_retourne_201(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/chantiers',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
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
        self::assertArrayHasKey('id', $data);
    }

    #[Test]
    public function post_avec_surface_cree_un_chantier_complet(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/chantiers',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'adresseRue' => '1 place Bellecour',
                'adresseCodePostal' => '69002',
                'adresseVille' => 'Lyon',
                'adressePays' => 'FR',
                'surfaceM2' => 75.5,
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);

        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertSame(75.5, $data['surfaceM2']);
    }

    #[Test]
    public function post_avec_donnees_invalides_retourne_422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/chantiers',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'adresseRue' => '',
                'adresseCodePostal' => '75002',
                'adresseVille' => 'Paris',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function get_item_retourne_le_chantier(): void
    {
        $client = static::createClient();
        $entite = ChantierFactory::createOne();

        $client->request('GET', '/api/chantiers/' . $entite->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);

        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertSame($entite->id->toRfc4122(), $data['id']);
    }

    #[Test]
    public function get_item_retourne_404_si_id_inconnu(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/chantiers/00000000-0000-7000-8000-000000000001', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function patch_modifie_le_chantier(): void
    {
        $client = static::createClient();
        $entite = ChantierFactory::createOne([
            'adresseRue' => '1 vieille rue',
            'adresseCodePostal' => '75001',
            'adresseVille' => 'Paris',
            'adressePays' => 'FR',
        ]);

        $client->request(
            'PATCH',
            '/api/chantiers/' . $entite->id->toRfc4122(),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/merge-patch+json',
            ],
            content: json_encode(['adresseRue' => '99 nouvelle rue']) ?: '',
        );

        self::assertResponseStatusCodeSame(200);

        $response = $client->getResponse()->getContent();
        \assert($response !== false);
        $data = self::decodeJson($response);
        self::assertSame('99 nouvelle rue', $data['adresseRue']);
    }

    #[Test]
    public function delete_archive_le_chantier(): void
    {
        $client = static::createClient();
        $entite = ChantierFactory::createOne();

        $client->request('DELETE', '/api/chantiers/' . $entite->id->toRfc4122());

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function delete_retourne_404_si_id_inconnu(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/chantiers/00000000-0000-7000-8000-000000000001');

        self::assertResponseStatusCodeSame(404);
    }
}
