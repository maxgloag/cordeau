<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Devis;

use App\Application\Devis\Validation\DemandeDevisInvalideException;
use App\Application\Devis\Validation\DemandeDevisJsonParser;
use App\Application\Devis\Validation\ErreurValidation;
use App\Domain\Devis\Enum\RegimeTva;
use App\Domain\Devis\Enum\TauxTva;
use App\Domain\Devis\Enum\Unite;
use App\Domain\Devis\ValueObject\RemiseGlobaleMontant;
use App\Domain\Devis\ValueObject\RemiseGlobalePourcentage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DemandeDevisJsonParserTest extends TestCase
{
    private const array LIGNE_VALIDE = [
        'designation' => 'Pose carrelage sol',
        'quantite' => '12.5',
        'unite' => 'M2',
        'prix_unitaire_ht' => '45.90',
        'taux_tva' => '10',
        'remise_pct' => '5',
    ];

    private const array CODES_LIGNE_NON_OBJET = ['DESIGNATION_VIDE', 'PRIX_INVALIDE', 'QUANTITE_INVALIDE', 'TAUX_INVALIDE', 'UNITE_INVALIDE'];

    #[Test]
    public function il_construit_la_demande_de_l_exemple_de_la_spec(): void
    {
        $demande = (new DemandeDevisJsonParser())->parse(self::json([
            'regime_tva' => 'NORMAL',
            'lignes' => [self::LIGNE_VALIDE],
            'remise_globale' => ['type' => 'POURCENTAGE', 'valeur' => '3'],
            'acompte_pct' => '30',
        ]));

        self::assertSame(RegimeTva::NORMAL, $demande->regime);
        $ligne = $demande->lignes->lignes[0];
        self::assertSame('Pose carrelage sol', $ligne->designation->libelle);
        self::assertSame('12.5', (string) $ligne->quantite->valeur);
        self::assertSame(Unite::M2, $ligne->unite);
        self::assertSame('45.90', (string) $ligne->prixUnitaireHt->valeur);
        self::assertSame(TauxTva::INTERMEDIAIRE, $ligne->tauxTva);
        self::assertSame('5', (string) $ligne->remise->valeur);
        self::assertInstanceOf(RemiseGlobalePourcentage::class, $demande->remiseGlobale);
        self::assertSame('3', (string) $demande->remiseGlobale->pourcentage->valeur);
        self::assertSame('30', (string) $demande->acompte->valeur);
    }

    #[Test]
    public function les_champs_optionnels_absents_ou_nuls_valent_zero_ou_aucune_remise(): void
    {
        $sansRemiseDeLigne = self::LIGNE_VALIDE;
        unset($sansRemiseDeLigne['remise_pct']);

        foreach ([[], ['remise_pct' => null, 'remise_globale' => null, 'acompte_pct' => null]] as $optionnels) {
            $demande = (new DemandeDevisJsonParser())->parse(self::json([
                'regime_tva' => 'NORMAL',
                'lignes' => [array_merge($sansRemiseDeLigne, array_intersect_key($optionnels, ['remise_pct' => true]))],
                ...array_diff_key($optionnels, ['remise_pct' => true]),
            ]));

            self::assertSame(0, $demande->lignes->lignes[0]->remise->valeur->compare(0));
            self::assertNull($demande->remiseGlobale);
            self::assertSame(0, $demande->acompte->valeur->compare(0));
        }
    }

    #[Test]
    public function une_remise_globale_en_montant_et_des_champs_inconnus_sont_acceptes(): void
    {
        $demande = (new DemandeDevisJsonParser())->parse(self::json([
            'regime_tva' => 'FRANCHISE_EN_BASE',
            'lignes' => [[...self::LIGNE_VALIDE, 'taux_tva' => '0', 'commentaire' => ['libre' => true]]],
            'remise_globale' => ['type' => 'MONTANT', 'valeur' => '545.06', 'note' => 1],
            'inconnu' => [[[[]]]],
        ]));

        self::assertSame(RegimeTva::FRANCHISE_EN_BASE, $demande->regime);
        self::assertInstanceOf(RemiseGlobaleMontant::class, $demande->remiseGlobale);
        self::assertSame('545.06', $demande->remiseGlobale->montant->enChaine());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function entreesInvalides(): iterable
    {
        $base = ['regime_tva' => 'NORMAL', 'lignes' => [self::LIGNE_VALIDE]];
        $ligne = static fn (array $surcharge): array => [...$base, 'lignes' => [[...self::LIGNE_VALIDE, ...$surcharge]]];
        $sansCle = static function (string $cle) use ($base): array {
            $ligne = self::LIGNE_VALIDE;
            unset($ligne[$cle]);

            return [...$base, 'lignes' => [$ligne]];
        };

        // --- JSON ---
        yield 'json mal forme' => ['{"regime_tva": "NORMAL",', ['JSON_INVALIDE']];
        yield 'entree vide' => ['', ['JSON_INVALIDE']];
        yield 'racine tableau' => ['[]', ['JSON_INVALIDE']];
        yield 'racine tableau non vide' => ['[{"regime_tva":"NORMAL"}]', ['JSON_INVALIDE']];
        yield 'racine null' => ['null', ['JSON_INVALIDE']];
        yield 'racine nombre' => ['42', ['JSON_INVALIDE']];
        yield 'racine chaine' => ['"{}"', ['JSON_INVALIDE']];
        yield 'virgule finale' => ['{"regime_tva":"NORMAL",}', ['JSON_INVALIDE']];

        // --- racine ---
        yield 'objet vide' => ['{}', ['LIGNES_VIDES', 'REGIME_INVALIDE']];
        yield 'regime en minuscules' => [self::json([...$base, 'regime_tva' => 'normal']), ['REGIME_INVALIDE']];
        yield 'regime null' => [self::json([...$base, 'regime_tva' => null]), ['REGIME_INVALIDE']];
        yield 'regime non chaine' => [self::json([...$base, 'regime_tva' => 1]), ['REGIME_INVALIDE']];
        yield 'lignes vides' => [self::json([...$base, 'lignes' => []]), ['LIGNES_VIDES']];
        yield 'lignes objet' => ['{"regime_tva":"NORMAL","lignes":{"0":'.self::json(self::LIGNE_VALIDE).'}}', ['LIGNES_VIDES']];
        yield 'lignes objet vide' => ['{"regime_tva":"NORMAL","lignes":{}}', ['LIGNES_VIDES']];
        yield 'lignes chaine' => [self::json([...$base, 'lignes' => 'x']), ['LIGNES_VIDES']];
        yield 'lignes null' => [self::json([...$base, 'lignes' => null]), ['LIGNES_VIDES']];

        // --- ligne non objet ---
        yield 'ligne chaine' => [self::json([...$base, 'lignes' => ['x']]), self::codesLigne(0, self::CODES_LIGNE_NON_OBJET)];
        yield 'ligne null' => [self::json([...$base, 'lignes' => [self::LIGNE_VALIDE, null]]), self::codesLigne(1, self::CODES_LIGNE_NON_OBJET)];
        yield 'ligne tableau' => [self::json([...$base, 'lignes' => [[]]]), self::codesLigne(0, self::CODES_LIGNE_NON_OBJET)];
        yield 'ligne objet vide' => ['{"regime_tva":"NORMAL","lignes":[{}]}', self::codesLigne(0, self::CODES_LIGNE_NON_OBJET)];

        // --- champs de ligne ---
        yield 'designation absente' => [self::json($sansCle('designation')), ['DESIGNATION_VIDE#0']];
        yield 'designation blanche' => [self::json($ligne(['designation' => " \t\n"])), ['DESIGNATION_VIDE#0']];
        yield 'designation non chaine' => [self::json($ligne(['designation' => 5])), ['DESIGNATION_VIDE#0']];
        yield 'quantite nombre json' => [self::json($ligne(['quantite' => 12.5])), ['QUANTITE_INVALIDE#0']];
        yield 'quantite nulle' => [self::json($ligne(['quantite' => '0'])), ['QUANTITE_INVALIDE#0']];
        yield 'quantite 4 decimales' => [self::json($ligne(['quantite' => '1.0000'])), ['QUANTITE_INVALIDE#0']];
        yield 'quantite au dessus de la borne' => [self::json($ligne(['quantite' => '1000000.001'])), ['QUANTITE_INVALIDE#0']];
        yield 'quantite avec retour ligne' => [self::json($ligne(['quantite' => "12\n"])), ['QUANTITE_INVALIDE#0']];
        yield 'quantite null' => [self::json($ligne(['quantite' => null])), ['QUANTITE_INVALIDE#0']];
        yield 'unite en minuscules' => [self::json($ligne(['unite' => 'm2'])), ['UNITE_INVALIDE#0']];
        yield 'unite absente' => [self::json($sansCle('unite')), ['UNITE_INVALIDE#0']];
        yield 'prix 3 decimales' => [self::json($ligne(['prix_unitaire_ht' => '1.001'])), ['PRIX_INVALIDE#0']];
        yield 'prix au dessus de la borne' => [self::json($ligne(['prix_unitaire_ht' => '10000000.01'])), ['PRIX_INVALIDE#0']];
        yield 'prix negatif' => [self::json($ligne(['prix_unitaire_ht' => '-1'])), ['PRIX_INVALIDE#0']];
        yield 'taux non canonique' => [self::json($ligne(['taux_tva' => '5.50'])), ['TAUX_INVALIDE#0']];
        yield 'taux nombre json' => [self::json($ligne(['taux_tva' => 20])), ['TAUX_INVALIDE#0']];
        yield 'taux absent' => [self::json($sansCle('taux_tva')), ['TAUX_INVALIDE#0']];
        yield 'remise de ligne au dessus de 100' => [self::json($ligne(['remise_pct' => '100.01'])), ['REMISE_LIGNE_INVALIDE#0']];
        yield 'remise de ligne nombre json' => [self::json($ligne(['remise_pct' => 5])), ['REMISE_LIGNE_INVALIDE#0']];
        yield 'remise de ligne chaine vide' => [self::json($ligne(['remise_pct' => ''])), ['REMISE_LIGNE_INVALIDE#0']];
        yield 'remise de ligne 3 decimales' => [self::json($ligne(['remise_pct' => '1.000'])), ['REMISE_LIGNE_INVALIDE#0']];

        // --- franchise en base ---
        $franchise = [...$base, 'regime_tva' => 'FRANCHISE_EN_BASE'];
        yield 'franchise avec un taux non nul' => [
            self::json([...$franchise, 'lignes' => [[...self::LIGNE_VALIDE, 'taux_tva' => '0'], self::LIGNE_VALIDE]]),
            ['FRANCHISE_AVEC_TVA#1'],
        ];
        yield 'franchise avec un taux invalide : seul le taux est signale' => [
            self::json([...$franchise, 'lignes' => [[...self::LIGNE_VALIDE, 'taux_tva' => '7']]]),
            ['TAUX_INVALIDE#0'],
        ];
        yield 'franchise avec taux non nul et autre champ invalide sur la meme ligne' => [
            self::json([...$franchise, 'lignes' => [[...self::LIGNE_VALIDE, 'taux_tva' => '20', 'quantite' => '0']]]),
            ['FRANCHISE_AVEC_TVA#0', 'QUANTITE_INVALIDE#0'],
        ];
        yield 'regime invalide : pas de controle de franchise' => [
            self::json([...$base, 'regime_tva' => 'FRANCHISE', 'lignes' => [[...self::LIGNE_VALIDE, 'taux_tva' => '20']]]),
            ['REGIME_INVALIDE'],
        ];

        // --- remise globale ---
        yield 'remise globale chaine' => [self::json([...$base, 'remise_globale' => '3']), ['REMISE_GLOBALE_INVALIDE']];
        yield 'remise globale tableau' => [self::json([...$base, 'remise_globale' => []]), ['REMISE_GLOBALE_INVALIDE']];
        yield 'remise globale objet vide' => ['{"regime_tva":"NORMAL","lignes":['.self::json(self::LIGNE_VALIDE).'],"remise_globale":{}}', ['REMISE_GLOBALE_INVALIDE']];
        yield 'remise globale type inconnu' => [self::json([...$base, 'remise_globale' => ['type' => 'pourcentage', 'valeur' => '3']]), ['REMISE_GLOBALE_INVALIDE']];
        yield 'remise globale sans valeur' => [self::json([...$base, 'remise_globale' => ['type' => 'MONTANT']]), ['REMISE_GLOBALE_INVALIDE']];
        yield 'remise globale valeur nombre json' => [self::json([...$base, 'remise_globale' => ['type' => 'MONTANT', 'valeur' => 10]]), ['REMISE_GLOBALE_INVALIDE']];
        yield 'remise globale pourcentage au dessus de 100' => [self::json([...$base, 'remise_globale' => ['type' => 'POURCENTAGE', 'valeur' => '100.01']]), ['REMISE_GLOBALE_INVALIDE']];
        yield 'remise globale montant 3 decimales' => [self::json([...$base, 'remise_globale' => ['type' => 'MONTANT', 'valeur' => '1.001']]), ['REMISE_GLOBALE_INVALIDE']];

        // --- remise superieure au total (total HT brut de la ligne valide : 545.06) ---
        $remiseTropForte = ['type' => 'MONTANT', 'valeur' => '545.07'];
        yield 'remise montant superieure au total' => [self::json([...$base, 'remise_globale' => $remiseTropForte]), ['REMISE_SUPERIEURE_AU_TOTAL']];
        yield 'remise superieure au total malgre un regime invalide' => [
            self::json([...$base, 'regime_tva' => 'X', 'remise_globale' => $remiseTropForte]),
            ['REGIME_INVALIDE', 'REMISE_SUPERIEURE_AU_TOTAL'],
        ];
        yield 'remise superieure au total malgre un acompte invalide' => [
            self::json([...$base, 'remise_globale' => $remiseTropForte, 'acompte_pct' => '101']),
            ['ACOMPTE_INVALIDE', 'REMISE_SUPERIEURE_AU_TOTAL'],
        ];
        yield 'remise superieure au total non verifiee si une ligne est invalide' => [
            self::json([...$base, 'lignes' => [self::LIGNE_VALIDE, [...self::LIGNE_VALIDE, 'unite' => 'KG']], 'remise_globale' => $remiseTropForte]),
            ['UNITE_INVALIDE#1'],
        ];
        yield 'remise superieure au total non verifiee en cas de franchise avec tva' => [
            self::json([...$franchise, 'remise_globale' => $remiseTropForte]),
            ['FRANCHISE_AVEC_TVA#0'],
        ];
        yield 'remise superieure au total non verifiee sans lignes' => [
            self::json([...$base, 'lignes' => [], 'remise_globale' => $remiseTropForte]),
            ['LIGNES_VIDES'],
        ];
        yield 'remise pourcentage a 100 jamais superieure au total' => [
            self::json([...$base, 'remise_globale' => ['type' => 'POURCENTAGE', 'valeur' => '100'], 'acompte_pct' => 'x']),
            ['ACOMPTE_INVALIDE'],
        ];

        // --- acompte ---
        yield 'acompte au dessus de 100' => [self::json([...$base, 'acompte_pct' => '100.01']), ['ACOMPTE_INVALIDE']];
        yield 'acompte nombre json' => [self::json([...$base, 'acompte_pct' => 30]), ['ACOMPTE_INVALIDE']];
        yield 'acompte negatif' => [self::json([...$base, 'acompte_pct' => '-1']), ['ACOMPTE_INVALIDE']];
        yield 'acompte booleen' => [self::json([...$base, 'acompte_pct' => false]), ['ACOMPTE_INVALIDE']];

        // --- cumul ---
        yield 'toutes les erreurs sont rapportees avec leur index de ligne' => [
            self::json([
                'regime_tva' => 'AUTRE',
                'lignes' => [
                    self::LIGNE_VALIDE,
                    [...self::LIGNE_VALIDE, 'quantite' => '-1', 'prix_unitaire_ht' => '1.234'],
                    42,
                ],
                'remise_globale' => ['type' => 'MONTANT', 'valeur' => '-5'],
                'acompte_pct' => '1e2',
            ]),
            [
                'ACOMPTE_INVALIDE', 'PRIX_INVALIDE#1', 'QUANTITE_INVALIDE#1', 'REGIME_INVALIDE', 'REMISE_GLOBALE_INVALIDE',
                ...self::codesLigne(2, self::CODES_LIGNE_NON_OBJET),
            ],
        ];
    }

    /**
     * @param list<string> $erreursAttendues
     */
    #[Test]
    #[DataProvider('entreesInvalides')]
    public function il_rapporte_toutes_les_erreurs_detectables(string $json, array $erreursAttendues): void
    {
        sort($erreursAttendues);

        self::assertSame($erreursAttendues, self::erreursDe($json));
    }

    #[Test]
    public function une_remise_en_montant_egale_au_total_est_acceptee(): void
    {
        $demande = (new DemandeDevisJsonParser())->parse(self::json([
            'regime_tva' => 'NORMAL',
            'lignes' => [self::LIGNE_VALIDE],
            'remise_globale' => ['type' => 'MONTANT', 'valeur' => '545.06'],
        ]));

        self::assertInstanceOf(RemiseGlobaleMontant::class, $demande->remiseGlobale);
    }

    #[Test]
    public function une_imbrication_profonde_dans_un_champ_inconnu_reste_un_json_valide(): void
    {
        $profond = str_repeat('[', 600).str_repeat(']', 600);
        $json = '{"regime_tva":"NORMAL","lignes":['.self::json(self::LIGNE_VALIDE).'],"x":'.$profond.'}';

        self::assertSame(RegimeTva::NORMAL, (new DemandeDevisJsonParser())->parse($json)->regime);
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private static function codesLigne(int $index, array $codes): array
    {
        return array_map(static fn (string $code): string => $code.'#'.$index, $codes);
    }

    /**
     * @return list<string> erreurs triées, au format CODE ou CODE#index
     */
    private static function erreursDe(string $json): array
    {
        try {
            (new DemandeDevisJsonParser())->parse($json);
        } catch (DemandeDevisInvalideException $e) {
            $erreurs = array_map(
                static fn (ErreurValidation $erreur): string => $erreur->code->value.($erreur->ligne === null ? '' : '#'.$erreur->ligne),
                $e->erreurs,
            );
            sort($erreurs);

            return $erreurs;
        }

        self::fail('Une DemandeDevisInvalideException était attendue.');
    }

    private static function json(mixed $valeur): string
    {
        return json_encode($valeur, \JSON_THROW_ON_ERROR);
    }
}
