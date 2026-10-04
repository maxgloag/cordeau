<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Messenger;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Garde #124 : un message routé vers `async` n'est traité que si un process consomme la file.
 * En test le transport est `in-memory://`, donc l'absence de consommateur en prod ne se voit nulle part
 * ailleurs : les vignettes restaient à `null` et les objets R2 supprimés n'étaient jamais effacés.
 */
final class ConsommateurMessengerTest extends TestCase
{
    private const string API = __DIR__ . '/../../../..';

    #[Test]
    public function un_message_route_vers_async_a_un_consommateur_declare_dans_fly_toml(): void
    {
        /** @var array{framework: array{messenger: array{routing: array<string, string>}}} $config */
        $config = Yaml::parseFile(self::API . '/config/packages/messenger.yaml');
        $routesAsync = array_keys(array_filter(
            $config['framework']['messenger']['routing'],
            static fn (string $transport): bool => $transport === 'async',
        ));
        self::assertNotEmpty($routesAsync, 'aucun message routé vers async : ce garde n\'a plus lieu d\'être');

        $flyToml = (string) file_get_contents(self::API . '/fly.toml');

        self::assertMatchesRegularExpression(
            '/^\s*worker\s*=\s*".*messenger:consume\s+async\b/m',
            $flyToml,
            'fly.toml ne déclare aucun process qui exécute messenger:consume async : ' . implode(', ', $routesAsync),
        );
    }

    #[Test]
    public function le_worker_est_relance_quand_il_sort_proprement(): void
    {
        $flyToml = (string) file_get_contents(self::API . '/fly.toml');

        // --time-limit fait sortir le worker avec le code 0 : la politique par défaut (on-failure) ne le relance pas.
        self::assertMatchesRegularExpression(
            '/\[\[restart\]\]\s+policy\s*=\s*"always"\s+processes\s*=\s*\["worker"\]/',
            $flyToml,
        );
    }
}
