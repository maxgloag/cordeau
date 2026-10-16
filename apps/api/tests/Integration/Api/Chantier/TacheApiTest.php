<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Chantier;

use App\Entity\User;
use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\LotFactory;
use App\Tests\Factory\TacheFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\Api\JsonTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class TacheApiTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    #[Test]
    public function post_cree_une_tache_a_faire_dans_le_lot(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $client->loginUser($user);
        $uuid = Uuid::v7()->toRfc4122();

        self::postJson($client, '/api/lots/' . $lotId . '/taches', ['libelle' => 'Poncer les murs', 'ordre' => 2, 'uuid' => $uuid]);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame($uuid, $data['id']);
        self::assertSame($lotId, $data['lotId']);
        self::assertSame('Poncer les murs', $data['libelle']);
        self::assertFalse($data['faite']);
        self::assertNull($data['faiteLe']);
        self::assertSame(2, $data['ordre']);
    }

    #[Test]
    public function post_avec_uuid_deja_pris_retourne_409_et_ne_deplace_pas_la_tache(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $autreLot = LotFactory::createOne();
        $existante = TacheFactory::createOne(['lotId' => $autreLot->id]);
        $client->loginUser($user);

        self::postJson($client, '/api/lots/' . $lotId . '/taches', ['libelle' => 'Doublon', 'uuid' => $existante->id->toRfc4122()]);

        self::assertResponseStatusCodeSame(409);
        TacheFactory::assert()->count(1, ['lotId' => $autreLot->id]);
        TacheFactory::assert()->count(0, ['lotId' => Uuid::fromString($lotId)]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function payloadsInvalides(): iterable
    {
        yield 'libelle vide' => [['libelle' => '  ']];
        yield 'libelle absent' => [[]];
        yield 'libelle trop long' => [['libelle' => str_repeat('a', 256)]];
        yield 'ordre negatif' => [['libelle' => 'Tache', 'ordre' => -1]];
        yield 'uuid invalide' => [['libelle' => 'Tache', 'uuid' => 'pas-un-uuid']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('payloadsInvalides')]
    public function post_invalide_retourne_422_et_ne_cree_rien(array $payload): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $client->loginUser($user);

        self::postJson($client, '/api/lots/' . $lotId . '/taches', $payload);

        self::assertResponseStatusCodeSame(422);
        TacheFactory::assert()->count(0);
    }

    #[Test]
    public function post_sur_un_lot_inexistant_retourne_404(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());

        self::postJson($client, '/api/lots/' . Uuid::v7()->toRfc4122() . '/taches', ['libelle' => 'Tache']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function get_collection_liste_les_taches_du_lot_par_ordre(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        TacheFactory::createOne(['lotId' => Uuid::fromString($lotId), 'libelle' => 'Peindre', 'ordre' => 2]);
        TacheFactory::createOne(['lotId' => Uuid::fromString($lotId), 'libelle' => 'Poncer', 'ordre' => 1]);
        TacheFactory::createOne(['libelle' => 'Autre lot']);
        $client->loginUser($user);

        $client->request('GET', '/api/lots/' . $lotId . '/taches', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $taches = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame(['Poncer', 'Peindre'], array_map(static function (mixed $tache): mixed {
            \assert(\is_array($tache));

            return $tache['libelle'];
        }, $taches));
    }

    #[Test]
    public function patch_faite_coche_puis_decoche_la_tache(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $tache = TacheFactory::createOne(['lotId' => Uuid::fromString($lotId)]);
        $client->loginUser($user);

        self::patchJson($client, '/api/taches/' . $tache->id->toRfc4122(), ['faite' => true]);

        self::assertResponseStatusCodeSame(200);
        $cochee = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertTrue($cochee['faite']);
        self::assertIsString($cochee['faiteLe']);

        self::patchJson($client, '/api/taches/' . $tache->id->toRfc4122(), ['faite' => false]);

        self::assertResponseStatusCodeSame(200);
        $decochee = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertFalse($decochee['faite']);
        self::assertNull($decochee['faiteLe']);
    }

    #[Test]
    public function patch_faite_sur_une_tache_deja_faite_garde_sa_date(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $faiteLe = new \DateTimeImmutable('2026-10-01T08:00:00+00:00');
        $tache = TacheFactory::createOne(['lotId' => Uuid::fromString($lotId), 'faite' => true, 'faiteLe' => $faiteLe]);
        $client->loginUser($user);

        // Rejeu de l'outbox mobile : la même mutation peut arriver deux fois.
        self::patchJson($client, '/api/taches/' . $tache->id->toRfc4122(), ['faite' => true]);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        \assert(\is_string($data['faiteLe']));
        self::assertSame($faiteLe->getTimestamp(), (new \DateTimeImmutable($data['faiteLe']))->getTimestamp());
    }

    #[Test]
    public function patch_libelle_renomme_sans_changer_l_etat(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $faiteLe = new \DateTimeImmutable('2026-10-01T08:00:00+00:00');
        $tache = TacheFactory::createOne(['lotId' => Uuid::fromString($lotId), 'faite' => true, 'faiteLe' => $faiteLe]);
        $client->loginUser($user);

        self::patchJson($client, '/api/taches/' . $tache->id->toRfc4122(), ['libelle' => 'Nouveau libellé']);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame('Nouveau libellé', $data['libelle']);
        self::assertTrue($data['faite']);
        \assert(\is_string($data['faiteLe']));
        self::assertSame($faiteLe->getTimestamp(), (new \DateTimeImmutable($data['faiteLe']))->getTimestamp());
    }

    #[Test]
    public function patch_libelle_vide_retourne_422(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $tache = TacheFactory::createOne(['lotId' => Uuid::fromString($lotId)]);
        $client->loginUser($user);

        self::patchJson($client, '/api/taches/' . $tache->id->toRfc4122(), ['libelle' => '']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function patch_tache_inexistante_retourne_404(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());

        self::patchJson($client, '/api/taches/' . Uuid::v7()->toRfc4122(), ['faite' => true]);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function delete_supprime_la_tache(): void
    {
        $client = static::createClient();
        [$user, $lotId] = self::lotDUnArtisan();
        $tache = TacheFactory::createOne(['lotId' => Uuid::fromString($lotId)]);
        $autre = TacheFactory::createOne(['lotId' => Uuid::fromString($lotId)]);
        $client->loginUser($user);

        $client->request('DELETE', '/api/taches/' . $tache->id->toRfc4122());

        self::assertResponseStatusCodeSame(204);
        TacheFactory::assert()->notExists(['id' => $tache->id]);
        TacheFactory::assert()->exists(['id' => $autre->id]);
    }

    /**
     * @return array{User, string}
     */
    private static function lotDUnArtisan(): array
    {
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $lot = LotFactory::createOne(['chantierId' => $chantier->id]);

        return [$user->_real(), $lot->id->toRfc4122()];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function postJson(KernelBrowser $client, string $uri, array $payload): void
    {
        $client->request(
            'POST',
            $uri,
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload) ?: '',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function patchJson(KernelBrowser $client, string $uri, array $payload): void
    {
        $client->request(
            'PATCH',
            $uri,
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode($payload) ?: '',
        );
    }
}
