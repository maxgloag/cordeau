<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineUserRepository;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CreateUserCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private function tester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('app:user:create'));
    }

    #[Test]
    public function cree_un_compte_utilisable_avec_le_mot_de_passe_fourni(): void
    {
        $tester = $this->tester();

        $tester->execute(['email' => 'testeur@cordeau.fr', '--mot-de-passe' => 'Password1']);

        $tester->assertCommandIsSuccessful();

        $userRepo = self::getContainer()->get(DoctrineUserRepository::class);
        \assert($userRepo instanceof DoctrineUserRepository);
        $user = $userRepo->findByEmail('testeur@cordeau.fr');
        self::assertNotNull($user);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);
        self::assertTrue($hasher->isPasswordValid($user, 'Password1'));
    }

    #[Test]
    public function echoue_sans_creer_de_doublon_si_email_deja_utilise(): void
    {
        UserFactory::createOne(['email' => 'deja@cordeau.fr']);

        $tester = $this->tester();
        $exitCode = $tester->execute(['email' => 'deja@cordeau.fr', '--mot-de-passe' => 'Password1']);

        self::assertSame(Command::FAILURE, $exitCode);

        $userRepo = self::getContainer()->get(DoctrineUserRepository::class);
        \assert($userRepo instanceof DoctrineUserRepository);
        self::assertSame(1, $userRepo->count(['email' => 'deja@cordeau.fr']));
    }

    #[Test]
    public function genere_un_mot_de_passe_utilisable_si_non_fourni(): void
    {
        $tester = $this->tester();
        $tester->execute(['email' => 'genere@cordeau.fr']);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        self::assertMatchesRegularExpression('/Mot de passe/i', $output);

        // Le mot de passe affiche doit reellement permettre de s'authentifier.
        self::assertSame(1, preg_match('/Mot de passe[^:]*:\s*(\S+)/i', $output, $m));
        $motDePasseGenere = $m[1];

        $userRepo = self::getContainer()->get(DoctrineUserRepository::class);
        \assert($userRepo instanceof DoctrineUserRepository);
        $user = $userRepo->findByEmail('genere@cordeau.fr');
        self::assertNotNull($user);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);
        self::assertTrue($hasher->isPasswordValid($user, $motDePasseGenere));
    }
}
