<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Devis\UseCase\CalculerTotauxDevisUseCase;
use App\Application\Devis\Validation\DemandeDevisInvalideException;
use App\Application\Devis\Validation\ErreurValidation;
use App\Domain\Devis\ValueObject\Montant;
use App\Domain\Devis\ValueObject\TotauxDevis;
use App\Domain\Devis\ValueObject\VentilationTva;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Expérience B (étude « Rust et développement agentique ») : calcul des totaux d'un
 * devis. Lit la demande JSON sur l'entrée standard, écrit le résultat JSON sur la
 * sortie standard. Codes de sortie : 0 succès, 1 erreurs de validation,
 * 2 erreur interne (jamais 1, pour ne pas la confondre avec une entrée invalide).
 */
#[AsCommand(
    name: 'app:experience:totaux-devis',
    description: 'Calcule les totaux d\'un devis (JSON sur stdin → JSON sur stdout).',
)]
final class CalculerTotauxDevisCommand extends Command
{
    public const int CODE_ERREUR_INTERNE = 2;

    private const int OPTIONS_JSON = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

    public function __construct(
        private readonly CalculerTotauxDevisUseCase $calculerTotauxDevis,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $demande = stream_get_contents(($input instanceof StreamableInputInterface ? $input->getStream() : null) ?? \STDIN);
            if ($demande === false) {
                throw new \RuntimeException('Lecture de l\'entrée standard impossible.');
            }

            try {
                $resultat = ['ok' => self::totauxEnTableau($this->calculerTotauxDevis->execute($demande))];
                $codeSortie = Command::SUCCESS;
            } catch (DemandeDevisInvalideException $e) {
                $resultat = ['erreurs' => array_map(
                    static fn (ErreurValidation $erreur): array => ['code' => $erreur->code->value, 'ligne' => $erreur->ligne],
                    $e->erreurs,
                )];
                $codeSortie = Command::FAILURE;
            }

            // OUTPUT_RAW : pas d'interprétation des balises de style de la console.
            // VERBOSITY_QUIET : le JSON est LA sortie de la commande, écrite même avec -q.
            $output->writeln(json_encode($resultat, self::OPTIONS_JSON), OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);

            return $codeSortie;
        } catch (\Throwable $e) {
            $sortieErreur = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $sortieErreur->writeln(\sprintf('Erreur interne (%s) : %s', $e::class, $e->getMessage()), OutputInterface::OUTPUT_RAW);

            return self::CODE_ERREUR_INTERNE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function totauxEnTableau(TotauxDevis $totaux): array
    {
        return [
            'lignes' => array_map(static fn (Montant $montantHt): array => ['montant_ht' => $montantHt->enChaine()], $totaux->montantsHtLignes),
            'total_ht_brut' => $totaux->totalHtBrut->enChaine(),
            'remise_globale' => $totaux->remiseGlobale->enChaine(),
            'total_ht_net' => $totaux->totalHtNet->enChaine(),
            'ventilation_tva' => array_map(static fn (VentilationTva $ventilation): array => [
                'taux' => $ventilation->taux->value,
                'base_ht' => $ventilation->baseHt->enChaine(),
                'montant_tva' => $ventilation->montantTva->enChaine(),
            ], $totaux->ventilationTva),
            'total_tva' => $totaux->totalTva->enChaine(),
            'total_ttc' => $totaux->totalTtc->enChaine(),
            'acompte' => $totaux->acompte->enChaine(),
            'solde' => $totaux->solde->enChaine(),
            'mention_tva' => $totaux->mentionTva,
        ];
    }
}
