<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api\Chantier;

use App\Domain\Chantier\Enum\ModeFacturation;
use App\Tests\Factory\ChantierFactory;
use App\Tests\Factory\LotFactory;
use App\Tests\Factory\TacheFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Integration\Api\JsonTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LotApiTest extends WebTestCase
{
    use Factories;
    use JsonTestHelper;
    use ResetDatabase;

    #[Test]
    public function post_cree_un_lot_surface_avec_estimation_et_expose_son_unite(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $client->loginUser($user->_real());

        self::postJson($client, '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', [
            'nom' => 'Peinture salon',
            'mode' => 'surface',
            'estimation' => 25.5,
            'ordre' => 1,
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame($chantier->id->toRfc4122(), $data['chantierId']);
        self::assertSame('Peinture salon', $data['nom']);
        self::assertSame('surface', $data['mode']);
        self::assertSame('m²', $data['unite']);
        self::assertSame(25.5, $data['estimation']);
        self::assertNull($data['reel']);
        self::assertSame(1, $data['ordre']);
        self::assertSame([], $data['imprevus']);
    }

    #[Test]
    public function post_avec_uuid_client_conserve_l_identifiant(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $client->loginUser($user->_real());
        $uuid = Uuid::v7()->toRfc4122();

        self::postJson($client, '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', [
            'nom' => 'Placo',
            'mode' => 'temps',
            'uuid' => $uuid,
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame($uuid, $data['id']);
        self::assertSame('h', $data['unite']);
        self::assertSame(0, $data['ordre']);
    }

    #[Test]
    public function post_avec_uuid_deja_pris_retourne_409_et_ne_modifie_pas_le_lot_existant(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $existant = LotFactory::createOne(['chantierId' => $chantier->id, 'nom' => 'Existant']);
        $client->loginUser($user->_real());

        self::postJson($client, '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', [
            'nom' => 'Doublon',
            'mode' => 'forfait',
            'uuid' => $existant->id->toRfc4122(),
        ]);

        self::assertResponseStatusCodeSame(409);
        $client->request('GET', '/api/lots/' . $existant->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame('Existant', self::decodeJson((string) $client->getResponse()->getContent())['nom']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function payloadsInvalides(): iterable
    {
        yield 'nom vide' => [['nom' => '   ', 'mode' => 'forfait']];
        yield 'nom absent' => [['mode' => 'forfait']];
        yield 'mode inconnu' => [['nom' => 'Lot', 'mode' => 'au-pif']];
        yield 'mode absent' => [['nom' => 'Lot']];
        yield 'estimation negative' => [['nom' => 'Lot', 'mode' => 'temps', 'estimation' => -1]];
        yield 'estimation hors colonne' => [['nom' => 'Lot', 'mode' => 'forfait', 'estimation' => 1e12]];
        yield 'ordre negatif' => [['nom' => 'Lot', 'mode' => 'forfait', 'ordre' => -1]];
        yield 'uuid invalide' => [['nom' => 'Lot', 'mode' => 'forfait', 'uuid' => 'pas-un-uuid']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('payloadsInvalides')]
    public function post_invalide_retourne_422_et_ne_cree_rien(array $payload): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $client->loginUser($user->_real());

        self::postJson($client, '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', $payload);

        self::assertResponseStatusCodeSame(422);
        LotFactory::assert()->count(0);
    }

    #[Test]
    public function post_sans_auth_retourne_401(): void
    {
        $client = static::createClient();
        $chantier = ChantierFactory::createOne();

        self::postJson($client, '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', ['nom' => 'Lot', 'mode' => 'forfait']);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function post_sur_un_chantier_inexistant_retourne_404(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());

        self::postJson($client, '/api/chantiers/' . Uuid::v7()->toRfc4122() . '/lots', ['nom' => 'Lot', 'mode' => 'forfait']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function get_collection_liste_les_lots_du_chantier_par_ordre(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        LotFactory::createOne(['chantierId' => $chantier->id, 'nom' => 'Second', 'ordre' => 2]);
        LotFactory::createOne(['chantierId' => $chantier->id, 'nom' => 'Premier', 'ordre' => 1]);
        LotFactory::createOne(['nom' => 'Autre chantier']);
        $client->loginUser($user->_real());

        $client->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $lots = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame(['Premier', 'Second'], array_column($lots, 'nom'));
    }

    #[Test]
    public function get_collection_d_un_chantier_sans_lot_retourne_un_tableau_vide(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $client->loginUser($user->_real());

        $client->request('GET', '/api/chantiers/' . $chantier->id->toRfc4122() . '/lots', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], self::decodeJson((string) $client->getResponse()->getContent()));
    }

    #[Test]
    public function get_item_retourne_le_lot(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $lot = LotFactory::createOne([
            'chantierId' => $chantier->id,
            'mode' => ModeFacturation::TEMPS,
            'estimation' => '12.50',
            'reel' => '14.00',
        ]);
        $client->loginUser($user->_real());

        $client->request('GET', '/api/lots/' . $lot->id->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame($lot->id->toRfc4122(), $data['id']);
        self::assertSame('temps', $data['mode']);
        self::assertSame('h', $data['unite']);
        self::assertEquals(12.5, $data['estimation']);
        self::assertEquals(14.0, $data['reel']);
    }

    #[Test]
    public function get_item_inexistant_retourne_404(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::createOne()->_real());

        $client->request('GET', '/api/lots/' . Uuid::v7()->toRfc4122(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function patch_renomme_et_renseigne_estimation_et_reel(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $lot = LotFactory::createOne(['chantierId' => $chantier->id, 'nom' => 'Ancien', 'mode' => ModeFacturation::FORFAIT]);
        $client->loginUser($user->_real());

        self::patchJson($client, '/api/lots/' . $lot->id->toRfc4122(), ['nom' => 'Nouveau', 'estimation' => 1200, 'reel' => 1350.5]);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame('Nouveau', $data['nom']);
        self::assertSame('forfait', $data['mode']);
        self::assertSame('€', $data['unite']);
        self::assertEquals(1200, $data['estimation']);
        self::assertEquals(1350.5, $data['reel']);
    }

    #[Test]
    public function patch_qui_change_le_mode_reinitialise_estimation_et_reel(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $lot = LotFactory::createOne([
            'chantierId' => $chantier->id,
            'mode' => ModeFacturation::SURFACE,
            'estimation' => '25.00',
            'reel' => '30.00',
        ]);
        $client->loginUser($user->_real());

        self::patchJson($client, '/api/lots/' . $lot->id->toRfc4122(), ['mode' => 'temps']);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame('temps', $data['mode']);
        self::assertSame('h', $data['unite']);
        self::assertNull($data['estimation']);
        self::assertNull($data['reel']);
    }

    #[Test]
    public function patch_mode_et_estimation_ensemble_garde_l_estimation_dans_le_nouveau_mode(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $lot = LotFactory::createOne(['chantierId' => $chantier->id, 'mode' => ModeFacturation::SURFACE, 'estimation' => '25.00']);
        $client->loginUser($user->_real());

        self::patchJson($client, '/api/lots/' . $lot->id->toRfc4122(), ['mode' => 'temps', 'estimation' => 8]);

        self::assertResponseStatusCodeSame(200);
        $data = self::decodeJson((string) $client->getResponse()->getContent());
        self::assertSame('temps', $data['mode']);
        self::assertEquals(8, $data['estimation']);
    }

    #[Test]
    public function patch_invalide_retourne_422(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $lot = LotFactory::createOne(['chantierId' => $chantier->id]);
        $client->loginUser($user->_real());

        self::patchJson($client, '/api/lots/' . $lot->id->toRfc4122(), ['reel' => -3]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function delete_supprime_le_lot_et_ses_taches_en_cascade(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $chantier = ChantierFactory::createOne(['proprietaire' => $user]);
        $lot = LotFactory::createOne(['chantierId' => $chantier->id]);
        $autreLot = LotFactory::createOne(['chantierId' => $chantier->id]);
        TacheFactory::createMany(2, ['lotId' => $lot->id]);
        TacheFactory::createOne(['lotId' => $autreLot->id]);
        $client->loginUser($user->_real());

        $client->request('DELETE', '/api/lots/' . $lot->id->toRfc4122());

        self::assertResponseStatusCodeSame(204);
        LotFactory::assert()->notExists(['id' => $lot->id]);
        TacheFactory::assert()->count(0, ['lotId' => $lot->id]);
        TacheFactory::assert()->count(1, ['lotId' => $autreLot->id]);
    }
}
