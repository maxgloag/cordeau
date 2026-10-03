<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Observability;

use App\Domain\Chantier\Exception\ChantierIntrouvableException;
use App\Infrastructure\Observability\SentryBeforeSend;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Uid\Uuid;

final class SentryBeforeSendTest extends TestCase
{
    private SentryBeforeSend $filtre;

    protected function setUp(): void
    {
        $this->filtre = new SentryBeforeSend([ChantierIntrouvableException::class => 404]);
    }

    #[Test]
    public function il_ecarte_une_erreur_http_client(): void
    {
        $exception = new ConflictHttpException('Déjà existant.');

        self::assertNull(($this->filtre)(Event::createEvent(), EventHint::fromArray(['exception' => $exception])));
    }

    #[Test]
    public function il_garde_une_erreur_http_serveur(): void
    {
        $exception = new HttpException(503, 'Indisponible.');

        self::assertNotNull(($this->filtre)(Event::createEvent(), EventHint::fromArray(['exception' => $exception])));
    }

    #[Test]
    public function il_ecarte_une_exception_du_domaine_mappee_en_4xx(): void
    {
        $exception = ChantierIntrouvableException::avecId(Uuid::v7());

        self::assertNull(($this->filtre)(Event::createEvent(), EventHint::fromArray(['exception' => $exception])));
    }

    #[Test]
    public function il_ecarte_aussi_une_erreur_client_enveloppee_dans_une_autre_exception(): void
    {
        $enveloppe = new \RuntimeException('Enveloppe', 0, new ConflictHttpException('Déjà existant.'));

        self::assertNull(($this->filtre)(Event::createEvent(), EventHint::fromArray(['exception' => $enveloppe])));
    }

    #[Test]
    public function il_garde_une_exception_inattendue(): void
    {
        $exception = new \RuntimeException('Boum');

        self::assertNotNull(($this->filtre)(Event::createEvent(), EventHint::fromArray(['exception' => $exception])));
    }

    #[Test]
    public function il_ne_conserve_que_les_entetes_autorises_et_retire_le_reste_de_la_requete(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'method' => 'POST',
            'url' => 'https://cordeau-api.fly.dev/auth/oauth/google/callback?code=SECRET&state=ETAT',
            'query_string' => 'code=SECRET&state=ETAT',
            'headers' => [
                'Authorization' => 'Bearer jeton-secret',
                'Cookie' => 'PHPSESSID=secret',
                'User-Agent' => 'Cordeau/1.0',
                'Content-Type' => 'application/json',
                'X-Forwarded-For' => '203.0.113.7',
            ],
            'cookies' => ['PHPSESSID' => 'secret'],
            'data' => ['email' => 'a@b.fr', 'motDePasse' => 'Password1'],
            'env' => ['REMOTE_ADDR' => '203.0.113.7'],
        ]);

        $resultat = ($this->filtre)($event, EventHint::fromArray(['exception' => new \RuntimeException('x')]));

        self::assertNotNull($resultat);
        $requete = $resultat->getRequest();
        self::assertSame(['User-Agent' => 'Cordeau/1.0', 'Content-Type' => 'application/json'], $requete['headers']);
        self::assertSame('https://cordeau-api.fly.dev/auth/oauth/google/callback', $requete['url']);
        self::assertSame('POST', $requete['method']);
        foreach (['cookies', 'data', 'env', 'query_string'] as $cle) {
            self::assertArrayNotHasKey($cle, $requete, $cle . ' ne doit pas être envoyé');
        }
        self::assertStringNotContainsString('SECRET', json_encode($requete, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('203.0.113.7', json_encode($requete, \JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function il_masque_emails_et_telephones_dans_les_messages(): void
    {
        $event = Event::createEvent();
        $event->setMessage('Échec pour jean.dupont@exemple.fr au 06 12 34 56 78');
        $event->setExceptions([
            new ExceptionDataBag(new \RuntimeException('Téléphone invalide : +33612345678 (jean@exemple.fr)')),
        ]);

        $resultat = ($this->filtre)($event);

        self::assertNotNull($resultat);
        self::assertSame('Échec pour [email] au [tel]', $resultat->getMessage());
        self::assertSame('Téléphone invalide : [tel] ([email])', $resultat->getExceptions()[0]->getValue());
    }

    #[Test]
    public function il_retire_les_arguments_des_fonctions_des_traces(): void
    {
        // Une trace passant par isPasswordValid($user, $motDePasse) contiendrait le mot de passe en clair.
        $donnees = new ExceptionDataBag(new \RuntimeException('Boum'));
        $donnees->setStacktrace(new Stacktrace([
            new Frame('isPasswordValid', 'Hasher.php', 12, vars: ['plainPassword' => 'Password1', 'email' => 'a@b.fr']),
            new Frame('__invoke', 'LoginController.php', 40, vars: ['motDePasse' => 'Password1']),
        ]));
        $event = Event::createEvent();
        $event->setExceptions([$donnees]);

        $resultat = ($this->filtre)($event, EventHint::fromArray(['exception' => new \RuntimeException('x')]));

        self::assertNotNull($resultat);
        $stacktrace = $resultat->getExceptions()[0]->getStacktrace();
        self::assertNotNull($stacktrace);
        self::assertCount(2, $stacktrace->getFrames());
        foreach ($stacktrace->getFrames() as $frame) {
            self::assertSame([], $frame->getVars());
        }
    }

    #[Test]
    public function il_retire_l_utilisateur(): void
    {
        $event = Event::createEvent();
        $event->setUser(\Sentry\UserDataBag::createFromUserIdentifier('abc'));

        $resultat = ($this->filtre)($event, EventHint::fromArray(['exception' => new \RuntimeException('x')]));

        self::assertNotNull($resultat);
        self::assertNull($resultat->getUser());
    }
}
