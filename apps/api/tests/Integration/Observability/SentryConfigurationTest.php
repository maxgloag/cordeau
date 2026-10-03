<?php

declare(strict_types=1);

namespace App\Tests\Integration\Observability;

use App\Infrastructure\Observability\SentryBeforeSend;
use PHPUnit\Framework\Attributes\Test;
use Sentry\State\HubInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que la configuration de `config/packages/sentry.yaml` est réellement appliquée par le bundle
 * (une clé mal orthographiée serait ignorée silencieusement ou rejetée au démarrage).
 */
final class SentryConfigurationTest extends KernelTestCase
{
    #[Test]
    public function le_client_sentry_applique_les_options_de_confidentialite(): void
    {
        self::bootKernel();
        $hub = static::getContainer()->get(HubInterface::class);
        \assert($hub instanceof HubInterface);
        $client = $hub->getClient();
        self::assertNotNull($client);
        $options = $client->getOptions();

        self::assertFalse($options->shouldSendDefaultPii());
        self::assertSame(0, $options->getMaxBreadcrumbs());
        self::assertSame('none', $options->getMaxRequestBodySize());
        self::assertSame(0.0, $options->getTracesSampleRate());
        self::assertInstanceOf(SentryBeforeSend::class, $options->getBeforeSendCallback());
    }

    #[Test]
    public function sans_dsn_aucun_evenement_n_est_envoye(): void
    {
        self::bootKernel();
        $hub = static::getContainer()->get(HubInterface::class);
        \assert($hub instanceof HubInterface);
        $client = $hub->getClient();
        self::assertNotNull($client);

        self::assertNull($client->getOptions()->getDsn());
    }
}
