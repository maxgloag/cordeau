<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Auth\Repository\DoctrineUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Onboarding manuel pendant la beta privee (cf ADR 0021) : cree un compte
 * sans passer par l'inscription self-service fermee. Ne depend pas du flag
 * REGISTRATION_SELF_SERVICE_ENABLED — c'est le canal admin de creation de comptes.
 */
#[AsCommand(
    name: 'app:user:create',
    description: 'Cree un compte utilisateur manuellement (onboarding beta, cf ADR 0021).',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly DoctrineUserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email du compte a creer')
            ->addOption('mot-de-passe', null, InputOption::VALUE_REQUIRED, 'Mot de passe (genere et affiche si absent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        if (!\is_string($email)) {
            $io->error('Email invalide.');

            return Command::FAILURE;
        }

        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(),
            new Assert\Email(),
            new Assert\Length(max: 255),
        ]);
        if (\count($violations) > 0) {
            $io->error('Email invalide.');

            return Command::FAILURE;
        }

        if ($this->userRepository->emailExiste($email)) {
            $io->error(sprintf('Un compte existe déjà avec l\'email %s.', $email));

            return Command::FAILURE;
        }

        $motDePasseOption = $input->getOption('mot-de-passe');
        $genere = !\is_string($motDePasseOption) || $motDePasseOption === '';
        $motDePasse = $genere ? $this->genererMotDePasse() : $motDePasseOption;

        $user = new User(Uuid::v7(), $email, '');
        $user->motDePasseHash = $this->passwordHasher->hashPassword($user, $motDePasse);
        $this->userRepository->save($user);

        $io->success(sprintf('Compte créé : %s', $email));
        if ($genere) {
            $io->writeln(sprintf('Mot de passe généré : <comment>%s</comment>', $motDePasse));
            $io->writeln('Transmettez-le au testeur par un canal sûr, puis invitez-le à le changer.');
        }

        return Command::SUCCESS;
    }

    private function genererMotDePasse(): string
    {
        // Prefixe 'A1' => garantit une majuscule et un chiffre (regles register), puis
        // 16 caracteres alphanumeriques tires aleatoirement (random_int est cryptographique).
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $motDePasse = 'A1';
        for ($i = 0; $i < 16; ++$i) {
            $motDePasse .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }

        return $motDePasse;
    }
}
