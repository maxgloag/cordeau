<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Filtre appliqué à chaque événement avant son envoi à Sentry (cf ADR 0028).
 *
 * - écarte les erreurs client (4xx) : ce sont des réponses normales de l'API, pas des pannes ;
 * - ne conserve que des en-têtes inoffensifs (liste blanche) et retire cookies, corps, query string
 *   et variables serveur (dont l'IP cliente) ;
 * - masque emails et numéros de téléphone dans les messages d'erreur ;
 * - retire les arguments des fonctions des traces (mots de passe, tokens, emails).
 */
final class SentryBeforeSend
{
    /** En-têtes conservés (minuscules) ; tout le reste (Authorization, Cookie…) est supprimé. */
    private const ENTETES_AUTORISES = ['host', 'user-agent', 'content-type', 'accept', 'x-client-type'];

    private const MOTIF_EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    private const MOTIF_TELEPHONE = '/(?:\+33|0033|0)[\s.\-]*[1-9](?:[\s.\-]*\d{2}){4}/';

    /**
     * @param array<class-string, int> $exceptionToStatus correspondance exception → statut HTTP d'API Platform
     */
    public function __construct(private readonly array $exceptionToStatus)
    {
    }

    public function __invoke(Event $event, ?EventHint $hint = null): ?Event
    {
        for ($exception = $hint?->exception; $exception !== null; $exception = $exception->getPrevious()) {
            if ($this->estUneErreurClient($exception)) {
                return null;
            }
        }

        $event->setRequest($this->nettoyerRequete($event->getRequest()));
        $event->setUser(null);

        $message = $event->getMessage();
        if ($message !== null) {
            $event->setMessage($this->masquer($message));
        }

        foreach ($event->getExceptions() as $donnees) {
            $donnees->setValue($this->masquer($donnees->getValue()));

            // Les arguments des fonctions de la trace peuvent contenir des mots de passe, des emails,
            // des tokens (ex. isPasswordValid($user, $motDePasse)) : on ne garde que le chemin d'appel.
            foreach ($donnees->getStacktrace()?->getFrames() ?? [] as $frame) {
                $frame->setVars([]);
            }
        }

        return $event;
    }

    private function estUneErreurClient(\Throwable $exception): bool
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode() < 500;
        }

        if ($exception instanceof ProblemExceptionInterface) {
            $statut = $exception->getStatus();
            if ($statut !== null) {
                return $statut < 500;
            }
        }

        foreach ($this->exceptionToStatus as $classe => $statut) {
            if ($exception instanceof $classe) {
                return $statut < 500;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $requete
     *
     * @return array<string, mixed>
     */
    private function nettoyerRequete(array $requete): array
    {
        $propre = [];

        foreach (['method'] as $cle) {
            if (isset($requete[$cle])) {
                $propre[$cle] = $requete[$cle];
            }
        }

        if (isset($requete['url']) && \is_string($requete['url'])) {
            $propre['url'] = strstr($requete['url'], '?', true) ?: $requete['url'];
        }

        if (isset($requete['headers']) && \is_array($requete['headers'])) {
            $propre['headers'] = array_filter(
                $requete['headers'],
                static fn (string|int $nom): bool => \in_array(strtolower((string) $nom), self::ENTETES_AUTORISES, true),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return $propre;
    }

    private function masquer(string $texte): string
    {
        $texte = preg_replace(self::MOTIF_EMAIL, '[email]', $texte) ?? $texte;

        return preg_replace(self::MOTIF_TELEPHONE, '[tel]', $texte) ?? $texte;
    }
}
