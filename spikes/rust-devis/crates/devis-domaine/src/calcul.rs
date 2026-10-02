//! Calcul des totaux d'une [`DemandeDevis`] valide (fonction totale : une
//! demande valide a toujours des totaux).

use std::collections::BTreeMap;

use num_bigint::BigInt;

use crate::arrondi::{DenominateurPositif, arrondir};
use crate::demande::{DemandeDevis, LigneDevis, RemiseGlobale};
use crate::montant::Montant;
use crate::valeurs::{Pourcentage, RegimeTva, TauxTva};

/// Mention légale obligatoire en franchise en base de TVA.
pub const MENTION_FRANCHISE: &str = "TVA non applicable, art. 293 B du CGI";

/// Ligne de la ventilation de TVA.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct LigneVentilation {
    /// Taux de TVA.
    pub taux: TauxTva,
    /// Base HT nette (après remise globale).
    pub base_ht: Montant,
    /// TVA de la base.
    pub montant_tva: Montant,
}

/// Totaux d'un devis.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct TotauxDevis {
    /// Montant HT de chaque ligne, dans l'ordre de saisie.
    pub lignes: Vec<Montant>,
    /// Somme des montants HT des lignes.
    pub total_ht_brut: Montant,
    /// Remise globale effectivement appliquée.
    pub remise_globale: Montant,
    /// Total HT brut − remise globale.
    pub total_ht_net: Montant,
    /// Ventilation par taux croissant ; vide en franchise en base.
    pub ventilation_tva: Vec<LigneVentilation>,
    /// Somme des TVA par base.
    pub total_tva: Montant,
    /// Total HT net + total TVA.
    pub total_ttc: Montant,
    /// Acompte demandé.
    pub acompte: Montant,
    /// Total TTC − acompte.
    pub solde: Montant,
    /// Mention de TVA (franchise en base uniquement).
    pub mention_tva: Option<&'static str>,
}

/// Base HT d'un taux et part de remise globale qui lui est imputée.
struct BaseRemisee {
    taux: TauxTva,
    base: Montant,
    remise: Montant,
}

impl BaseRemisee {
    fn base_nette(&self) -> Montant {
        self.base.moins(&self.remise)
    }
}

impl DemandeDevis {
    /// Calcule les totaux, exacts au centime.
    #[must_use]
    pub fn calculer_totaux(&self) -> TotauxDevis {
        let lignes: Vec<Montant> = self.lignes().iter().map(LigneDevis::montant_ht).collect();
        let total_ht_brut: Montant = lignes.iter().sum();

        let mut bases: BTreeMap<TauxTva, Montant> = BTreeMap::new();
        for (ligne, montant) in self.lignes().iter().zip(&lignes) {
            let base = bases.entry(ligne.taux_tva()).or_default();
            *base = base.plus(montant);
        }

        let bases = ventiler_remise(self.remise_globale(), bases, &total_ht_brut);
        let remise_globale: Montant = bases.iter().map(|base| &base.remise).sum();
        let total_ht_net = total_ht_brut.moins(&remise_globale);

        let (ventilation_tva, mention_tva) = match self.regime() {
            RegimeTva::Normal => (bases.iter().map(ventiler_tva).collect(), None),
            RegimeTva::FranchiseEnBase => (Vec::new(), Some(MENTION_FRANCHISE)),
        };
        let total_tva: Montant = ventilation_tva
            .iter()
            .map(|ligne: &LigneVentilation| &ligne.montant_tva)
            .sum();
        let total_ttc = total_ht_net.plus(&total_tva);
        let acompte = appliquer_pourcentage(&total_ttc, self.acompte());
        let solde = total_ttc.moins(&acompte);

        TotauxDevis {
            lignes,
            total_ht_brut,
            remise_globale,
            total_ht_net,
            ventilation_tva,
            total_tva,
            total_ttc,
            acompte,
            solde,
            mention_tva,
        }
    }
}

/// Impute la remise globale à chaque base de TVA (règle 4 de la spec).
fn ventiler_remise(
    remise: Option<&RemiseGlobale>,
    bases: BTreeMap<TauxTva, Montant>,
    total_ht_brut: &Montant,
) -> Vec<BaseRemisee> {
    let sans_remise = |(taux, base)| BaseRemisee {
        taux,
        base,
        remise: Montant::zero(),
    };
    match remise {
        None => bases.into_iter().map(sans_remise).collect(),
        Some(RemiseGlobale::Pourcentage(pourcentage)) => bases
            .into_iter()
            .map(|(taux, base)| BaseRemisee {
                taux,
                remise: appliquer_pourcentage(&base, *pourcentage),
                base,
            })
            .collect(),
        Some(RemiseGlobale::Montant(montant)) => {
            // Total nul ⇒ montant nul (invariant M ≤ total) : toutes les parts à 0.
            let Some(total) = DenominateurPositif::nouveau(total_ht_brut.centimes().clone()) else {
                return bases.into_iter().map(sans_remise).collect();
            };
            let mut parts: Vec<BaseRemisee> = bases
                .into_iter()
                .map(|(taux, base)| BaseRemisee {
                    taux,
                    remise: Montant::depuis_centimes(arrondir(
                        &(montant.centimes() * base.centimes()),
                        &total,
                    )),
                    base,
                })
                .collect();
            let ecart = montant.moins(&parts.iter().map(|part| &part.remise).sum());
            // Base la plus élevée ; à égalité, celle du taux le plus élevé.
            if let Some(plus_grande) = parts
                .iter_mut()
                .max_by(|a, b| a.base.cmp(&b.base).then(a.taux.cmp(&b.taux)))
            {
                plus_grande.remise = plus_grande.remise.plus(&ecart);
            }
            parts
        }
    }
}

/// TVA d'une base nette (régime normal), calculée par base et jamais par ligne.
fn ventiler_tva(base: &BaseRemisee) -> LigneVentilation {
    let base_ht = base.base_nette();
    let numerateur = base_ht.centimes() * BigInt::from(base.taux.pour_mille());
    LigneVentilation {
        taux: base.taux,
        montant_tva: Montant::depuis_centimes(arrondir(
            &numerateur,
            &DenominateurPositif::puissance_de_dix(3),
        )),
        base_ht,
    }
}

/// `arrondi(montant × pourcentage / 100)`.
fn appliquer_pourcentage(montant: &Montant, pourcentage: Pourcentage) -> Montant {
    let numerateur = montant.centimes() * BigInt::from(pourcentage.centiemes());
    // Pourcentage en centièmes de % : 100 % = 10 000 = 10^4.
    Montant::depuis_centimes(arrondir(
        &numerateur,
        &DenominateurPositif::puissance_de_dix(4),
    ))
}

#[cfg(test)]
mod tests {
    use proptest::prelude::*;

    use super::*;
    use crate::demande::{ChampsLigneBruts, DemandeDevisBrute, LigneBrute, RemiseGlobaleBrute, Saisie};

    fn texte(valeur: &str) -> Saisie {
        Saisie::Texte(valeur.to_owned())
    }

    /// Ligne `(quantité, prix unitaire HT, taux)` sans remise de ligne.
    fn ligne(quantite: &str, prix: &str, taux: &str) -> LigneBrute {
        ligne_remisee(quantite, prix, taux, None)
    }

    fn ligne_remisee(quantite: &str, prix: &str, taux: &str, remise: Option<&str>) -> LigneBrute {
        LigneBrute::Objet(ChampsLigneBruts {
            designation: texte("Travaux"),
            quantite: texte(quantite),
            unite: texte("U"),
            prix_unitaire_ht: texte(prix),
            taux_tva: texte(taux),
            remise_pct: remise.map_or(Saisie::Absente, texte),
        })
    }

    fn remise(type_remise: &str, valeur: &str) -> RemiseGlobaleBrute {
        RemiseGlobaleBrute::Objet {
            type_remise: texte(type_remise),
            valeur: texte(valeur),
        }
    }

    fn totaux(
        regime: &str,
        lignes: Vec<LigneBrute>,
        remise_globale: RemiseGlobaleBrute,
        acompte: Option<&str>,
    ) -> TotauxDevis {
        DemandeDevis::valider(DemandeDevisBrute {
            regime_tva: texte(regime),
            lignes: Some(lignes),
            remise_globale,
            acompte_pct: acompte.map_or(Saisie::Absente, texte),
        })
        .expect("demande valide")
        .calculer_totaux()
    }

    /// Ventilation lisible : `(taux, base nette, TVA)`.
    fn ventilation(totaux: &TotauxDevis) -> Vec<(&'static str, String, String)> {
        totaux
            .ventilation_tva
            .iter()
            .map(|l| (l.taux.libelle(), l.base_ht.to_string(), l.montant_tva.to_string()))
            .collect()
    }

    fn v(taux: &'static str, base: &str, tva: &str) -> (&'static str, String, String) {
        (taux, base.to_owned(), tva.to_owned())
    }

    fn s(montant: &Montant) -> String {
        montant.to_string()
    }

    #[test]
    fn exemple_verifie_de_la_spec() {
        let t = totaux(
            "NORMAL",
            vec![ligne_remisee("12.5", "45.90", "10", Some("5"))],
            remise("POURCENTAGE", "3"),
            Some("30"),
        );
        assert_eq!(t.lignes.iter().map(s).collect::<Vec<_>>(), ["545.06"]);
        assert_eq!(s(&t.total_ht_brut), "545.06");
        assert_eq!(s(&t.remise_globale), "16.35");
        assert_eq!(s(&t.total_ht_net), "528.71");
        assert_eq!(ventilation(&t), [v("10", "528.71", "52.87")]);
        assert_eq!(s(&t.total_tva), "52.87");
        assert_eq!(s(&t.total_ttc), "581.58");
        assert_eq!(s(&t.acompte), "174.47");
        assert_eq!(s(&t.solde), "407.11");
        assert_eq!(t.mention_tva, None);
    }

    #[test]
    fn lignes_dans_l_ordre_et_ventilation_par_taux_croissant() {
        let t = totaux(
            "NORMAL",
            vec![
                ligne("1", "20", "20"),
                ligne("1", "1", "0"),
                ligne("1", "10", "10"),
                ligne("1", "5.50", "5.5"),
                ligne("2", "1", "20"),
            ],
            RemiseGlobaleBrute::Absente,
            None,
        );
        assert_eq!(
            t.lignes.iter().map(s).collect::<Vec<_>>(),
            ["20.00", "1.00", "10.00", "5.50", "2.00"]
        );
        assert_eq!(
            ventilation(&t),
            [
                v("0", "1.00", "0.00"),
                v("5.5", "5.50", "0.30"),
                v("10", "10.00", "1.00"),
                v("20", "22.00", "4.40"),
            ]
        );
        assert_eq!(s(&t.total_ht_brut), "38.50");
        assert_eq!(s(&t.remise_globale), "0.00");
        assert_eq!(s(&t.total_ht_net), "38.50");
        assert_eq!(s(&t.total_tva), "5.70");
        assert_eq!(s(&t.total_ttc), "44.20");
        assert_eq!(s(&t.acompte), "0.00");
        assert_eq!(s(&t.solde), "44.20");
    }

    #[test]
    fn tva_calculee_par_base_et_non_par_ligne() {
        // Par ligne : 2 × arrondi(0,005) = 0,02 ; par base : arrondi(0,01) = 0,01.
        let t = totaux(
            "NORMAL",
            vec![ligne("1", "0.05", "10"), ligne("1", "0.05", "10")],
            RemiseGlobaleBrute::Absente,
            None,
        );
        assert_eq!(ventilation(&t), [v("10", "0.10", "0.01")]);
        assert_eq!(s(&t.total_ttc), "0.11");
    }

    #[test]
    fn tva_a_5_5_arrondie_demi_superieur() {
        // 10,00 × 5,5 % = 0,55 ; 0,10 × 5,5 % = 0,0055 → 0,01.
        let t = totaux("NORMAL", vec![ligne("1", "0.10", "5.5")], RemiseGlobaleBrute::Absente, None);
        assert_eq!(ventilation(&t), [v("5.5", "0.10", "0.01")]);
    }

    #[test]
    fn remise_pourcentage_arrondie_base_par_base() {
        // 1 % de 0,50 = 0,005 → 0,01 par base, soit 0,02 (et non 1 % de 1,00).
        let t = totaux(
            "NORMAL",
            vec![ligne("1", "0.50", "10"), ligne("1", "0.50", "20")],
            remise("POURCENTAGE", "1"),
            None,
        );
        assert_eq!(s(&t.remise_globale), "0.02");
        assert_eq!(s(&t.total_ht_net), "0.98");
        assert_eq!(ventilation(&t), [v("10", "0.49", "0.05"), v("20", "0.49", "0.10")]);
        assert_eq!(s(&t.total_tva), "0.15");
        assert_eq!(s(&t.total_ttc), "1.13");
    }

    #[test]
    fn remise_pourcentage_de_100() {
        let t = totaux("NORMAL", vec![ligne("3", "7.77", "20")], remise("POURCENTAGE", "100"), Some("30"));
        assert_eq!(s(&t.remise_globale), "23.31");
        assert_eq!(s(&t.total_ht_net), "0.00");
        assert_eq!(ventilation(&t), [v("20", "0.00", "0.00")]);
        assert_eq!(s(&t.total_ttc), "0.00");
        assert_eq!(s(&t.acompte), "0.00");
        assert_eq!(s(&t.solde), "0.00");
    }

    #[test]
    fn remise_montant_ecart_reporte_sur_la_plus_grande_base() {
        // Parts : 1 × 50/300 = 0,1666… → 0,17 ; 1 × 200/300 → 0,67 ; 0,17.
        // Σ = 1,01 → écart −0,01 sur la base 10 % (la plus élevée) → 0,66.
        let t = totaux(
            "NORMAL",
            vec![ligne("1", "50", "5.5"), ligne("1", "200", "10"), ligne("1", "50", "20")],
            remise("MONTANT", "1"),
            None,
        );
        assert_eq!(s(&t.remise_globale), "1.00");
        assert_eq!(s(&t.total_ht_net), "299.00");
        assert_eq!(
            ventilation(&t),
            [
                v("5.5", "49.83", "2.74"),
                v("10", "199.34", "19.93"),
                v("20", "49.83", "9.97"),
            ]
        );
    }

    #[test]
    fn remise_montant_a_egalite_le_taux_le_plus_eleve_absorbe_l_ecart() {
        // 3 × arrondi(10/3) = 9,99 → écart +0,01 sur la base à 20 %.
        let t = totaux(
            "NORMAL",
            vec![ligne("1", "100", "5.5"), ligne("1", "100", "10"), ligne("1", "100", "20")],
            remise("MONTANT", "10"),
            None,
        );
        assert_eq!(s(&t.remise_globale), "10.00");
        assert_eq!(
            ventilation(&t),
            [
                v("5.5", "96.67", "5.32"),
                v("10", "96.67", "9.67"),
                v("20", "96.66", "19.33"),
            ]
        );
    }

    #[test]
    fn remise_montant_ecart_negatif_peut_rendre_une_part_negative() {
        // 4 bases de 1,00 ; M = 0,02 → chaque part arrondi(0,005) = 0,01 ;
        // Σ = 0,04 → écart −0,02 sur la base à 20 % → part −0,01.
        let t = totaux(
            "NORMAL",
            vec![
                ligne("1", "1", "0"),
                ligne("1", "1", "5.5"),
                ligne("1", "1", "10"),
                ligne("1", "1", "20"),
            ],
            remise("MONTANT", "0.02"),
            None,
        );
        assert_eq!(s(&t.remise_globale), "0.02");
        assert_eq!(s(&t.total_ht_net), "3.98");
        assert_eq!(
            ventilation(&t),
            [
                v("0", "0.99", "0.00"),
                v("5.5", "0.99", "0.05"),
                v("10", "0.99", "0.10"),
                v("20", "1.01", "0.20"),
            ]
        );
        assert_eq!(s(&t.total_tva), "0.35");
        assert_eq!(s(&t.total_ttc), "4.33");
    }

    #[test]
    fn remise_montant_sur_total_nul() {
        let t = totaux(
            "NORMAL",
            vec![ligne("1", "0", "10"), ligne("2", "0", "20")],
            remise("MONTANT", "0"),
            Some("50"),
        );
        assert_eq!(s(&t.total_ht_brut), "0.00");
        assert_eq!(s(&t.remise_globale), "0.00");
        assert_eq!(ventilation(&t), [v("10", "0.00", "0.00"), v("20", "0.00", "0.00")]);
        assert_eq!(s(&t.total_ttc), "0.00");
        assert_eq!(s(&t.solde), "0.00");
    }

    #[test]
    fn remise_montant_avec_une_base_nulle() {
        let t = totaux(
            "NORMAL",
            vec![ligne("1", "0", "5.5"), ligne("1", "100", "20")],
            remise("MONTANT", "10"),
            None,
        );
        assert_eq!(ventilation(&t), [v("5.5", "0.00", "0.00"), v("20", "90.00", "18.00")]);
    }

    #[test]
    fn remise_montant_egale_au_total() {
        let t = totaux(
            "NORMAL",
            vec![ligne("1", "33.33", "10"), ligne("1", "66.67", "20")],
            remise("MONTANT", "100"),
            None,
        );
        assert_eq!(s(&t.total_ht_net), "0.00");
        assert_eq!(ventilation(&t), [v("10", "0.00", "0.00"), v("20", "0.00", "0.00")]);
        assert_eq!(s(&t.total_ttc), "0.00");
    }

    #[test]
    fn franchise_en_base() {
        let t = totaux(
            "FRANCHISE_EN_BASE",
            vec![ligne("2", "100", "0"), ligne("1", "50.01", "0")],
            remise("POURCENTAGE", "10"),
            Some("50"),
        );
        assert_eq!(s(&t.total_ht_brut), "250.01");
        assert_eq!(s(&t.remise_globale), "25.00");
        assert_eq!(s(&t.total_ht_net), "225.01");
        assert!(t.ventilation_tva.is_empty());
        assert_eq!(s(&t.total_tva), "0.00");
        assert_eq!(s(&t.total_ttc), "225.01");
        // 225,01 × 50 % = 112,505 → 112,51.
        assert_eq!(s(&t.acompte), "112.51");
        assert_eq!(s(&t.solde), "112.50");
        assert_eq!(t.mention_tva, Some("TVA non applicable, art. 293 B du CGI"));
        assert_eq!(MENTION_FRANCHISE, "TVA non applicable, art. 293 B du CGI");
    }

    #[test]
    fn regime_normal_avec_taux_zero() {
        let t = totaux("NORMAL", vec![ligne("1", "1000", "0")], RemiseGlobaleBrute::Absente, None);
        assert_eq!(ventilation(&t), [v("0", "1000.00", "0.00")]);
        assert_eq!(t.mention_tva, None);
        assert_eq!(s(&t.total_ttc), "1000.00");
    }

    #[test]
    fn acompte_arrondi_demi_superieur() {
        // TTC 0,10 ; 5 % = 0,005 → 0,01.
        let t = totaux("NORMAL", vec![ligne("1", "0.10", "0")], RemiseGlobaleBrute::Absente, Some("5"));
        assert_eq!(s(&t.acompte), "0.01");
        assert_eq!(s(&t.solde), "0.09");
        let t = totaux("NORMAL", vec![ligne("1", "0.10", "0")], RemiseGlobaleBrute::Absente, Some("100"));
        assert_eq!(s(&t.acompte), "0.10");
        assert_eq!(s(&t.solde), "0.00");
        let t = totaux("NORMAL", vec![ligne("1", "0.10", "0")], RemiseGlobaleBrute::Absente, Some("4.99"));
        assert_eq!(s(&t.acompte), "0.00");
    }

    #[test]
    fn bornes_maximales_sans_debordement() {
        let lignes = ["0", "5.5", "10", "20"]
            .into_iter()
            .map(|taux| ligne("1000000", "10000000", taux))
            .collect();
        let t = totaux("NORMAL", lignes, remise("MONTANT", "39999999999999.99"), Some("100"));
        assert_eq!(s(&t.total_ht_brut), "40000000000000.00");
        assert_eq!(s(&t.total_ht_net), "0.01");
        assert_eq!(s(&t.solde), "0.00");
    }

    // --- Propriétés -------------------------------------------------------

    fn taux_quelconque() -> impl Strategy<Value = &'static str> {
        prop::sample::select(vec!["0", "5.5", "10", "20"])
    }

    fn montant_texte(max_centimes: u64) -> impl Strategy<Value = String> {
        (0..=max_centimes).prop_map(|c| format!("{}.{:02}", c / 100, c % 100))
    }

    fn ligne_quelconque() -> impl Strategy<Value = LigneBrute> {
        (1_u64..=5_000_000, montant_texte(10_000_000), taux_quelconque(), 0_u64..=10_000).prop_map(
            |(milliemes, prix, taux, remise)| {
                let quantite = format!("{}.{:03}", milliemes / 1000, milliemes % 1000);
                let remise = format!("{}.{:02}", remise / 100, remise % 100);
                ligne_remisee(&quantite, &prix, taux, Some(&remise))
            },
        )
    }

    fn somme<'a>(montants: impl Iterator<Item = &'a Montant>) -> Montant {
        montants.sum()
    }

    proptest! {
        #[test]
        fn totaux_coherents(
            lignes in prop::collection::vec(ligne_quelconque(), 1..8),
            pct in 0_u64..=10_000,
            fraction_montant in 0_u64..=1_000,
            type_remise in 0_u8..3,
            acompte in 0_u64..=10_000,
        ) {
            let brut = totaux("NORMAL", lignes.clone(), RemiseGlobaleBrute::Absente, None).total_ht_brut;
            let remise_montant = Montant::depuis_centimes(
                brut.centimes() * fraction_montant / 1000_u32,
            );
            let remise_globale = match type_remise {
                0 => RemiseGlobaleBrute::Absente,
                1 => remise("POURCENTAGE", &format!("{}.{:02}", pct / 100, pct % 100)),
                _ => remise("MONTANT", &remise_montant.to_string()),
            };
            let acompte = format!("{}.{:02}", acompte / 100, acompte % 100);
            let t = totaux("NORMAL", lignes, remise_globale, Some(&acompte));

            prop_assert_eq!(&somme(t.lignes.iter()), &t.total_ht_brut);
            prop_assert_eq!(&t.total_ht_net, &t.total_ht_brut.moins(&t.remise_globale));
            prop_assert_eq!(&somme(t.ventilation_tva.iter().map(|l| &l.base_ht)), &t.total_ht_net);
            prop_assert_eq!(&somme(t.ventilation_tva.iter().map(|l| &l.montant_tva)), &t.total_tva);
            prop_assert_eq!(&t.total_ttc, &t.total_ht_net.plus(&t.total_tva));
            prop_assert_eq!(&t.solde, &t.total_ttc.moins(&t.acompte));
            prop_assert!(t.acompte <= t.total_ttc && t.acompte >= Montant::zero());
            prop_assert!(t.ventilation_tva.windows(2).all(|p| p[0].taux < p[1].taux));
            if type_remise == 2 {
                prop_assert_eq!(&t.remise_globale, &remise_montant);
            }
            prop_assert!(t.total_ht_net >= Montant::zero());
        }
    }
}
