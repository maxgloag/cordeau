//! Frontière « saisie non fiable → domaine » : une [`DemandeDevisBrute`]
//! (chaînes non vérifiées fournies par un adaptateur) devient une
//! [`DemandeDevis`] valide, ou la liste exhaustive des erreurs détectables.

use num_bigint::BigInt;

use crate::arrondi::{DenominateurPositif, arrondir};
use crate::montant::Montant;
use crate::valeurs::{
    Designation, Pourcentage, PrixUnitaireHt, Quantite, RegimeTva, TauxTva, Unite,
};

/// Saisie brute d'un champ scalaire, telle que transmise par un adaptateur.
#[derive(Debug, Clone, Default, PartialEq, Eq)]
pub enum Saisie {
    /// Champ absent (ou explicitement nul).
    #[default]
    Absente,
    /// Champ présent sous forme de texte (non encore validé).
    Texte(String),
    /// Champ présent mais qui n'est pas du texte (nombre, booléen, objet…).
    NonTexte,
}

/// Champs bruts d'une ligne de devis.
#[derive(Debug, Clone, Default, PartialEq, Eq)]
pub struct ChampsLigneBruts {
    /// Désignation.
    pub designation: Saisie,
    /// Quantité (chaîne décimale).
    pub quantite: Saisie,
    /// Unité.
    pub unite: Saisie,
    /// Prix unitaire HT (chaîne décimale).
    pub prix_unitaire_ht: Saisie,
    /// Taux de TVA.
    pub taux_tva: Saisie,
    /// Remise de ligne en pourcentage (optionnelle).
    pub remise_pct: Saisie,
}

/// Ligne brute : un enregistrement à champs, ou une valeur illisible.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum LigneBrute {
    /// La ligne n'est pas un enregistrement à champs (ex. nombre, tableau).
    NonObjet,
    /// La ligne est un enregistrement à champs.
    Objet(ChampsLigneBruts),
}

/// Remise globale brute.
#[derive(Debug, Clone, Default, PartialEq, Eq)]
pub enum RemiseGlobaleBrute {
    /// Pas de remise globale (absente ou nulle).
    #[default]
    Absente,
    /// Présente mais pas un enregistrement à champs.
    NonObjet,
    /// Enregistrement `{ type, valeur }`.
    Objet {
        /// `"POURCENTAGE"` ou `"MONTANT"`.
        type_remise: Saisie,
        /// Chaîne décimale.
        valeur: Saisie,
    },
}

/// Demande de devis brute, non fiable.
#[derive(Debug, Clone, Default, PartialEq, Eq)]
pub struct DemandeDevisBrute {
    /// Régime de TVA.
    pub regime_tva: Saisie,
    /// Lignes ; `None` si absentes ou si ce n'est pas une liste.
    pub lignes: Option<Vec<LigneBrute>>,
    /// Remise globale.
    pub remise_globale: RemiseGlobaleBrute,
    /// Acompte en pourcentage (optionnel).
    pub acompte_pct: Saisie,
}

/// Erreur portant sur une ligne (toujours accompagnée de son index).
#[derive(Debug, Clone, Copy, PartialEq, Eq, PartialOrd, Ord, Hash)]
pub enum ErreurLigne {
    /// `DESIGNATION_VIDE`.
    DesignationVide,
    /// `QUANTITE_INVALIDE`.
    QuantiteInvalide,
    /// `UNITE_INVALIDE`.
    UniteInvalide,
    /// `PRIX_INVALIDE`.
    PrixInvalide,
    /// `TAUX_INVALIDE`.
    TauxInvalide,
    /// `REMISE_LIGNE_INVALIDE`.
    RemiseLigneInvalide,
    /// `FRANCHISE_AVEC_TVA`.
    FranchiseAvecTva,
}

impl ErreurLigne {
    /// Code d'erreur de la spec.
    #[must_use]
    pub fn code(self) -> &'static str {
        match self {
            Self::DesignationVide => "DESIGNATION_VIDE",
            Self::QuantiteInvalide => "QUANTITE_INVALIDE",
            Self::UniteInvalide => "UNITE_INVALIDE",
            Self::PrixInvalide => "PRIX_INVALIDE",
            Self::TauxInvalide => "TAUX_INVALIDE",
            Self::RemiseLigneInvalide => "REMISE_LIGNE_INVALIDE",
            Self::FranchiseAvecTva => "FRANCHISE_AVEC_TVA",
        }
    }
}

/// Erreur portant sur la demande dans son ensemble (sans index de ligne).
#[derive(Debug, Clone, Copy, PartialEq, Eq, PartialOrd, Ord, Hash)]
pub enum ErreurDemande {
    /// `REGIME_INVALIDE`.
    RegimeInvalide,
    /// `LIGNES_VIDES`.
    LignesVides,
    /// `REMISE_GLOBALE_INVALIDE`.
    RemiseGlobaleInvalide,
    /// `REMISE_SUPERIEURE_AU_TOTAL`.
    RemiseSuperieureAuTotal,
    /// `ACOMPTE_INVALIDE`.
    AcompteInvalide,
}

impl ErreurDemande {
    /// Code d'erreur de la spec.
    #[must_use]
    pub fn code(self) -> &'static str {
        match self {
            Self::RegimeInvalide => "REGIME_INVALIDE",
            Self::LignesVides => "LIGNES_VIDES",
            Self::RemiseGlobaleInvalide => "REMISE_GLOBALE_INVALIDE",
            Self::RemiseSuperieureAuTotal => "REMISE_SUPERIEURE_AU_TOTAL",
            Self::AcompteInvalide => "ACOMPTE_INVALIDE",
        }
    }
}

/// Erreur de validation : une erreur de ligne porte toujours un index, une
/// erreur de demande n'en porte jamais (garanti par le type).
#[derive(Debug, Clone, Copy, PartialEq, Eq, PartialOrd, Ord, Hash)]
pub enum ErreurValidation {
    /// Erreur globale.
    Demande(ErreurDemande),
    /// Erreur de la ligne d'index `index` (base 0).
    Ligne {
        /// Index de la ligne (base 0).
        index: usize,
        /// Nature de l'erreur.
        erreur: ErreurLigne,
    },
}

impl ErreurValidation {
    /// Code d'erreur de la spec.
    #[must_use]
    pub fn code(self) -> &'static str {
        match self {
            Self::Demande(erreur) => erreur.code(),
            Self::Ligne { erreur, .. } => erreur.code(),
        }
    }

    /// Index de ligne concerné, `None` pour une erreur globale.
    #[must_use]
    pub fn ligne(self) -> Option<usize> {
        match self {
            Self::Demande(_) => None,
            Self::Ligne { index, .. } => Some(index),
        }
    }
}

/// Ligne de devis valide.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct LigneDevis {
    designation: Designation,
    quantite: Quantite,
    unite: Unite,
    prix_unitaire_ht: PrixUnitaireHt,
    taux_tva: TauxTva,
    remise: Pourcentage,
}

impl LigneDevis {
    /// Désignation.
    #[must_use]
    pub fn designation(&self) -> &Designation {
        &self.designation
    }

    /// Unité.
    #[must_use]
    pub fn unite(&self) -> Unite {
        self.unite
    }

    /// Taux de TVA.
    #[must_use]
    pub fn taux_tva(&self) -> TauxTva {
        self.taux_tva
    }

    /// Montant HT = arrondi(quantité × prix unitaire × (1 − remise / 100)).
    #[must_use]
    pub fn montant_ht(&self) -> Montant {
        // En unités exactes : millièmes × centimes × centièmes de % restants,
        // soit des centimes × 10^-3 × 10^-4 → une seule division par 10^7.
        let reste_a_payer =
            BigInt::from(Pourcentage::CENT_POUR_CENT) - BigInt::from(self.remise.centiemes());
        let numerateur = BigInt::from(self.quantite.milliemes())
            * BigInt::from(self.prix_unitaire_ht.centimes())
            * reste_a_payer;
        Montant::depuis_centimes(arrondir(
            &numerateur,
            &DenominateurPositif::puissance_de_dix(7),
        ))
    }

    fn valider(ligne: &LigneBrute, regime: Option<RegimeTva>) -> Result<Self, Vec<ErreurLigne>> {
        let LigneBrute::Objet(champs) = ligne else {
            return Err(vec![
                ErreurLigne::DesignationVide,
                ErreurLigne::QuantiteInvalide,
                ErreurLigne::UniteInvalide,
                ErreurLigne::PrixInvalide,
                ErreurLigne::TauxInvalide,
            ]);
        };
        let designation =
            lire_requis(&champs.designation, Designation::lire).ok_or(ErreurLigne::DesignationVide);
        let quantite =
            lire_requis(&champs.quantite, Quantite::lire).ok_or(ErreurLigne::QuantiteInvalide);
        let unite = lire_requis(&champs.unite, Unite::lire).ok_or(ErreurLigne::UniteInvalide);
        let prix_unitaire_ht = lire_requis(&champs.prix_unitaire_ht, PrixUnitaireHt::lire)
            .ok_or(ErreurLigne::PrixInvalide);
        let taux_tva =
            lire_requis(&champs.taux_tva, TauxTva::lire).ok_or(ErreurLigne::TauxInvalide);
        let remise = lire_optionnel(&champs.remise_pct, Pourcentage::lire, Pourcentage::ZERO)
            .ok_or(ErreurLigne::RemiseLigneInvalide);
        let franchise = match (regime, taux_tva) {
            (Some(RegimeTva::FranchiseEnBase), Ok(taux)) if taux != TauxTva::Zero => {
                Err(ErreurLigne::FranchiseAvecTva)
            }
            _ => Ok(()),
        };
        match (
            designation,
            quantite,
            unite,
            prix_unitaire_ht,
            taux_tva,
            remise,
            franchise,
        ) {
            (
                Ok(designation),
                Ok(quantite),
                Ok(unite),
                Ok(prix_unitaire_ht),
                Ok(taux_tva),
                Ok(remise),
                Ok(()),
            ) => Ok(Self {
                designation,
                quantite,
                unite,
                prix_unitaire_ht,
                taux_tva,
                remise,
            }),
            (designation, quantite, unite, prix_unitaire_ht, taux_tva, remise, franchise) => Err([
                designation.err(),
                quantite.err(),
                unite.err(),
                prix_unitaire_ht.err(),
                taux_tva.err(),
                remise.err(),
                franchise.err(),
            ]
            .into_iter()
            .flatten()
            .collect()),
        }
    }
}

/// Remise globale valide.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum RemiseGlobale {
    /// Pourcentage appliqué à chaque base de TVA.
    Pourcentage(Pourcentage),
    /// Montant ventilé au prorata des bases ; jamais supérieur au total HT brut.
    Montant(Montant),
}

/// Demande de devis valide : constructible uniquement par [`DemandeDevis::valider`].
///
/// Invariants : au moins une ligne ; en franchise en base, toutes les lignes
/// sont à 0 % ; une remise globale en montant ne dépasse pas le total HT brut.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct DemandeDevis {
    regime: RegimeTva,
    lignes: Vec<LigneDevis>,
    remise_globale: Option<RemiseGlobale>,
    acompte: Pourcentage,
}

impl DemandeDevis {
    /// Valide une demande brute et rapporte **toutes** les erreurs détectables.
    ///
    /// # Errors
    ///
    /// Liste non vide des erreurs de validation (sans doublon).
    pub fn valider(brute: &DemandeDevisBrute) -> Result<Self, Vec<ErreurValidation>> {
        let regime = lire_requis(&brute.regime_tva, RegimeTva::lire)
            .ok_or(ErreurValidation::Demande(ErreurDemande::RegimeInvalide));
        let lignes = valider_lignes(brute.lignes.as_deref(), regime.ok());
        let remise_globale = valider_remise_globale(&brute.remise_globale).and_then(|remise| {
            // Contrôle croisé : uniquement si toutes les lignes sont valides,
            // indépendamment du régime et de l'acompte.
            match (&remise, &lignes) {
                (Some(RemiseGlobale::Montant(montant)), Ok(lignes))
                    if *montant > total_ht_brut(lignes) =>
                {
                    Err(ErreurValidation::Demande(
                        ErreurDemande::RemiseSuperieureAuTotal,
                    ))
                }
                _ => Ok(remise),
            }
        });
        let acompte = lire_optionnel(&brute.acompte_pct, Pourcentage::lire, Pourcentage::ZERO)
            .ok_or(ErreurValidation::Demande(ErreurDemande::AcompteInvalide));

        match (regime, lignes, remise_globale, acompte) {
            (Ok(regime), Ok(lignes), Ok(remise_globale), Ok(acompte)) => Ok(Self {
                regime,
                lignes,
                remise_globale,
                acompte,
            }),
            (regime, lignes, remise_globale, acompte) => {
                let mut erreurs: Vec<ErreurValidation> = regime.err().into_iter().collect();
                erreurs.extend(lignes.err().unwrap_or_default());
                erreurs.extend(remise_globale.err());
                erreurs.extend(acompte.err());
                Err(erreurs)
            }
        }
    }

    /// Régime de TVA.
    #[must_use]
    pub fn regime(&self) -> RegimeTva {
        self.regime
    }

    /// Lignes, dans l'ordre de saisie.
    #[must_use]
    pub fn lignes(&self) -> &[LigneDevis] {
        &self.lignes
    }

    /// Remise globale éventuelle.
    #[must_use]
    pub fn remise_globale(&self) -> Option<&RemiseGlobale> {
        self.remise_globale.as_ref()
    }

    /// Acompte en pourcentage du TTC.
    #[must_use]
    pub fn acompte(&self) -> Pourcentage {
        self.acompte
    }
}

/// Somme des montants HT des lignes.
pub(crate) fn total_ht_brut(lignes: &[LigneDevis]) -> Montant {
    lignes.iter().map(LigneDevis::montant_ht).sum()
}

/// Champ obligatoire : absent ou non texte → `None`.
fn lire_requis<T>(saisie: &Saisie, lire: impl FnOnce(&str) -> Option<T>) -> Option<T> {
    match saisie {
        Saisie::Texte(texte) => lire(texte),
        Saisie::Absente | Saisie::NonTexte => None,
    }
}

/// Champ optionnel : absent → `defaut` ; non texte → `None`.
fn lire_optionnel<T>(
    saisie: &Saisie,
    lire: impl FnOnce(&str) -> Option<T>,
    defaut: T,
) -> Option<T> {
    match saisie {
        Saisie::Absente => Some(defaut),
        Saisie::Texte(texte) => lire(texte),
        Saisie::NonTexte => None,
    }
}

/// Valide la liste de lignes : non vide, chaque ligne valide (erreurs indexées).
fn valider_lignes(
    lignes: Option<&[LigneBrute]>,
    regime: Option<RegimeTva>,
) -> Result<Vec<LigneDevis>, Vec<ErreurValidation>> {
    let lignes = match lignes {
        Some(lignes) if !lignes.is_empty() => lignes,
        _ => return Err(vec![ErreurValidation::Demande(ErreurDemande::LignesVides)]),
    };
    let mut valides = Vec::with_capacity(lignes.len());
    let mut erreurs = Vec::new();
    for (index, ligne) in lignes.iter().enumerate() {
        match LigneDevis::valider(ligne, regime) {
            Ok(valide) => valides.push(valide),
            Err(erreurs_ligne) => erreurs.extend(
                erreurs_ligne
                    .into_iter()
                    .map(|erreur| ErreurValidation::Ligne { index, erreur }),
            ),
        }
    }
    if erreurs.is_empty() {
        Ok(valides)
    } else {
        Err(erreurs)
    }
}

/// Valide la forme de la remise globale (sans le contrôle croisé au total).
fn valider_remise_globale(
    brute: &RemiseGlobaleBrute,
) -> Result<Option<RemiseGlobale>, ErreurValidation> {
    let remise = match brute {
        RemiseGlobaleBrute::Absente => return Ok(None),
        RemiseGlobaleBrute::Objet {
            type_remise: Saisie::Texte(type_remise),
            valeur: Saisie::Texte(valeur),
        } => match type_remise.as_str() {
            "POURCENTAGE" => Pourcentage::lire(valeur).map(RemiseGlobale::Pourcentage),
            "MONTANT" => Montant::lire(valeur).map(RemiseGlobale::Montant),
            _ => None,
        },
        // Non-objet, ou type / valeur absent ou non texte.
        RemiseGlobaleBrute::NonObjet | RemiseGlobaleBrute::Objet { .. } => None,
    };
    remise.map(Some).ok_or(ErreurValidation::Demande(
        ErreurDemande::RemiseGlobaleInvalide,
    ))
}

#[cfg(test)]
mod tests {
    use std::collections::BTreeSet;

    use super::*;

    fn texte(valeur: &str) -> Saisie {
        Saisie::Texte(valeur.to_owned())
    }

    fn champs_valides() -> ChampsLigneBruts {
        ChampsLigneBruts {
            designation: texte("Pose carrelage sol"),
            quantite: texte("12.5"),
            unite: texte("M2"),
            prix_unitaire_ht: texte("45.90"),
            taux_tva: texte("10"),
            remise_pct: texte("5"),
        }
    }

    fn ligne(modifier: impl FnOnce(&mut ChampsLigneBruts)) -> LigneBrute {
        let mut champs = champs_valides();
        modifier(&mut champs);
        LigneBrute::Objet(champs)
    }

    fn remise(type_remise: &str, valeur: &str) -> RemiseGlobaleBrute {
        RemiseGlobaleBrute::Objet {
            type_remise: texte(type_remise),
            valeur: texte(valeur),
        }
    }

    fn demande_valide() -> DemandeDevisBrute {
        DemandeDevisBrute {
            regime_tva: texte("NORMAL"),
            lignes: Some(vec![ligne(|_| {})]),
            remise_globale: remise("POURCENTAGE", "3"),
            acompte_pct: texte("30"),
        }
    }

    fn demande(modifier: impl FnOnce(&mut DemandeDevisBrute)) -> DemandeDevisBrute {
        let mut brute = demande_valide();
        modifier(&mut brute);
        brute
    }

    /// Erreurs sous forme ensembliste `(code, ligne)`, comme la spec les compare.
    fn erreurs(brute: &DemandeDevisBrute) -> BTreeSet<(&'static str, Option<usize>)> {
        let erreurs = DemandeDevis::valider(brute).expect_err("la demande aurait dû être refusée");
        let ensemble: BTreeSet<_> = erreurs.iter().map(|e| (e.code(), e.ligne())).collect();
        assert_eq!(ensemble.len(), erreurs.len(), "doublons : {erreurs:?}");
        ensemble
    }

    fn globale(code: &'static str) -> (&'static str, Option<usize>) {
        (code, None)
    }

    fn de_ligne(code: &'static str, index: usize) -> (&'static str, Option<usize>) {
        (code, Some(index))
    }

    fn ensemble<const N: usize>(
        elements: [(&'static str, Option<usize>); N],
    ) -> BTreeSet<(&'static str, Option<usize>)> {
        elements.into_iter().collect()
    }

    #[test]
    fn codes_d_erreur_de_la_spec() {
        assert_eq!(ErreurLigne::DesignationVide.code(), "DESIGNATION_VIDE");
        assert_eq!(ErreurLigne::QuantiteInvalide.code(), "QUANTITE_INVALIDE");
        assert_eq!(ErreurLigne::UniteInvalide.code(), "UNITE_INVALIDE");
        assert_eq!(ErreurLigne::PrixInvalide.code(), "PRIX_INVALIDE");
        assert_eq!(ErreurLigne::TauxInvalide.code(), "TAUX_INVALIDE");
        assert_eq!(
            ErreurLigne::RemiseLigneInvalide.code(),
            "REMISE_LIGNE_INVALIDE"
        );
        assert_eq!(ErreurLigne::FranchiseAvecTva.code(), "FRANCHISE_AVEC_TVA");
        assert_eq!(ErreurDemande::RegimeInvalide.code(), "REGIME_INVALIDE");
        assert_eq!(ErreurDemande::LignesVides.code(), "LIGNES_VIDES");
        assert_eq!(
            ErreurDemande::RemiseGlobaleInvalide.code(),
            "REMISE_GLOBALE_INVALIDE"
        );
        assert_eq!(
            ErreurDemande::RemiseSuperieureAuTotal.code(),
            "REMISE_SUPERIEURE_AU_TOTAL"
        );
        assert_eq!(ErreurDemande::AcompteInvalide.code(), "ACOMPTE_INVALIDE");
    }

    #[test]
    fn accepte_la_demande_de_l_exemple() {
        let valide = DemandeDevis::valider(&demande_valide()).expect("demande valide");
        assert_eq!(valide.regime(), RegimeTva::Normal);
        assert_eq!(valide.lignes().len(), 1);
        assert_eq!(valide.lignes()[0].taux_tva(), TauxTva::Dix);
        assert_eq!(valide.lignes()[0].unite(), Unite::M2);
        assert_eq!(
            valide.lignes()[0].designation().texte(),
            "Pose carrelage sol"
        );
        assert_eq!(
            valide.remise_globale(),
            Some(&RemiseGlobale::Pourcentage(Pourcentage::lire("3").unwrap()))
        );
        assert_eq!(valide.acompte(), Pourcentage::lire("30").unwrap());
    }

    #[test]
    fn champs_optionnels_absents_valent_zero() {
        let valide = DemandeDevis::valider(&demande(|d| {
            d.remise_globale = RemiseGlobaleBrute::Absente;
            d.acompte_pct = Saisie::Absente;
            d.lignes = Some(vec![ligne(|l| l.remise_pct = Saisie::Absente)]);
        }))
        .expect("demande valide");
        assert_eq!(valide.remise_globale(), None);
        assert_eq!(valide.acompte(), Pourcentage::ZERO);
        // 12,5 × 45,90 = 573,75 sans remise de ligne.
        assert_eq!(
            valide.lignes()[0].montant_ht(),
            Montant::depuis_centimes(57_375)
        );
    }

    #[test]
    fn regime_absent_non_texte_ou_inconnu() {
        for saisie in [
            Saisie::Absente,
            Saisie::NonTexte,
            texte("normal"),
            texte(""),
        ] {
            assert_eq!(
                erreurs(&demande(|d| d.regime_tva = saisie.clone())),
                ensemble([globale("REGIME_INVALIDE")]),
                "{saisie:?}"
            );
        }
    }

    #[test]
    fn lignes_absentes_ou_vides() {
        for lignes in [None, Some(vec![])] {
            assert_eq!(
                erreurs(&demande(|d| d.lignes = lignes.clone())),
                ensemble([globale("LIGNES_VIDES")])
            );
        }
    }

    #[test]
    fn ligne_non_objet_produit_cinq_erreurs() {
        assert_eq!(
            erreurs(&demande(
                |d| d.lignes = Some(vec![ligne(|_| {}), LigneBrute::NonObjet])
            )),
            ensemble([
                de_ligne("DESIGNATION_VIDE", 1),
                de_ligne("QUANTITE_INVALIDE", 1),
                de_ligne("UNITE_INVALIDE", 1),
                de_ligne("PRIX_INVALIDE", 1),
                de_ligne("TAUX_INVALIDE", 1),
            ])
        );
    }

    #[test]
    fn ligne_aux_champs_tous_absents() {
        assert_eq!(
            erreurs(&demande(
                |d| d.lignes = Some(vec![LigneBrute::Objet(ChampsLigneBruts::default())])
            )),
            ensemble([
                de_ligne("DESIGNATION_VIDE", 0),
                de_ligne("QUANTITE_INVALIDE", 0),
                de_ligne("UNITE_INVALIDE", 0),
                de_ligne("PRIX_INVALIDE", 0),
                de_ligne("TAUX_INVALIDE", 0),
            ])
        );
    }

    #[test]
    fn chaque_champ_de_ligne_a_son_code() {
        type Modif = fn(&mut ChampsLigneBruts);
        let cas: [(Modif, &str); 12] = [
            (|l| l.designation = texte("   "), "DESIGNATION_VIDE"),
            (|l| l.designation = Saisie::NonTexte, "DESIGNATION_VIDE"),
            (|l| l.quantite = texte("0"), "QUANTITE_INVALIDE"),
            (|l| l.quantite = texte("1.2345"), "QUANTITE_INVALIDE"),
            (|l| l.quantite = Saisie::NonTexte, "QUANTITE_INVALIDE"),
            (|l| l.unite = texte("KG"), "UNITE_INVALIDE"),
            (|l| l.prix_unitaire_ht = texte("1.234"), "PRIX_INVALIDE"),
            (
                |l| l.prix_unitaire_ht = texte("10000000.01"),
                "PRIX_INVALIDE",
            ),
            (|l| l.taux_tva = texte("5.50"), "TAUX_INVALIDE"),
            (|l| l.taux_tva = Saisie::NonTexte, "TAUX_INVALIDE"),
            (|l| l.remise_pct = texte("100.01"), "REMISE_LIGNE_INVALIDE"),
            (|l| l.remise_pct = Saisie::NonTexte, "REMISE_LIGNE_INVALIDE"),
        ];
        for (modifier, code) in cas {
            assert_eq!(
                erreurs(&demande(
                    |d| d.lignes = Some(vec![ligne(|_| {}), ligne(modifier)])
                )),
                ensemble([de_ligne(code, 1)]),
                "{code}"
            );
        }
    }

    #[test]
    fn franchise_exige_un_taux_nul_sur_chaque_ligne() {
        let franchise = |lignes: Vec<LigneBrute>| {
            demande(|d| {
                d.regime_tva = texte("FRANCHISE_EN_BASE");
                d.lignes = Some(lignes);
            })
        };
        let a_zero = || ligne(|l| l.taux_tva = texte("0"));
        assert!(DemandeDevis::valider(&franchise(vec![a_zero(), a_zero()])).is_ok());
        assert_eq!(
            erreurs(&franchise(vec![
                a_zero(),
                ligne(|l| l.taux_tva = texte("20"))
            ])),
            ensemble([de_ligne("FRANCHISE_AVEC_TVA", 1)])
        );
        // Taux invalide : pas de FRANCHISE_AVEC_TVA en plus.
        assert_eq!(
            erreurs(&franchise(vec![ligne(|l| l.taux_tva = texte("7"))])),
            ensemble([de_ligne("TAUX_INVALIDE", 0)])
        );
        // Taux valide non nul + autre erreur sur la même ligne : les deux.
        assert_eq!(
            erreurs(&franchise(vec![ligne(|l| l.unite = Saisie::Absente)])),
            ensemble([
                de_ligne("UNITE_INVALIDE", 0),
                de_ligne("FRANCHISE_AVEC_TVA", 0)
            ])
        );
    }

    #[test]
    fn regime_invalide_ne_declenche_pas_le_controle_de_franchise() {
        assert_eq!(
            erreurs(&demande(|d| d.regime_tva = texte("FRANCHISE"))),
            ensemble([globale("REGIME_INVALIDE")])
        );
    }

    #[test]
    fn remise_globale_valide() {
        for brute in [
            RemiseGlobaleBrute::Absente,
            remise("POURCENTAGE", "100"),
            remise("POURCENTAGE", "0"),
            remise("MONTANT", "0"),
            remise("MONTANT", "545.06"),
        ] {
            assert!(
                DemandeDevis::valider(&demande(|d| d.remise_globale = brute.clone())).is_ok(),
                "{brute:?}"
            );
        }
    }

    #[test]
    fn remise_globale_invalide() {
        for brute in [
            RemiseGlobaleBrute::NonObjet,
            RemiseGlobaleBrute::Objet {
                type_remise: Saisie::Absente,
                valeur: texte("3"),
            },
            RemiseGlobaleBrute::Objet {
                type_remise: texte("MONTANT"),
                valeur: Saisie::Absente,
            },
            RemiseGlobaleBrute::Objet {
                type_remise: texte("MONTANT"),
                valeur: Saisie::NonTexte,
            },
            RemiseGlobaleBrute::Objet {
                type_remise: Saisie::NonTexte,
                valeur: texte("3"),
            },
            remise("pourcentage", "3"),
            remise("FIXE", "3"),
            remise("POURCENTAGE", "100.01"),
            remise("POURCENTAGE", "3.333"),
            remise("MONTANT", "1.234"),
            remise("MONTANT", "-1"),
            remise("MONTANT", "1e3"),
        ] {
            assert_eq!(
                erreurs(&demande(|d| d.remise_globale = brute.clone())),
                ensemble([globale("REMISE_GLOBALE_INVALIDE")]),
                "{brute:?}"
            );
        }
    }

    #[test]
    fn acompte_optionnel_entre_0_et_100() {
        assert!(DemandeDevis::valider(&demande(|d| d.acompte_pct = texte("100"))).is_ok());
        for saisie in [
            Saisie::NonTexte,
            texte("100.5"),
            texte("1.234"),
            texte("-1"),
            texte(""),
        ] {
            assert_eq!(
                erreurs(&demande(|d| d.acompte_pct = saisie.clone())),
                ensemble([globale("ACOMPTE_INVALIDE")]),
                "{saisie:?}"
            );
        }
    }

    #[test]
    fn remise_montant_superieure_au_total_brut() {
        // Total HT brut de l'exemple : 545,06.
        assert!(
            DemandeDevis::valider(&demande(|d| d.remise_globale = remise("MONTANT", "545.06")))
                .is_ok()
        );
        assert_eq!(
            erreurs(&demande(|d| d.remise_globale = remise("MONTANT", "545.07"))),
            ensemble([globale("REMISE_SUPERIEURE_AU_TOTAL")])
        );
        assert_eq!(
            erreurs(&demande(|d| {
                d.remise_globale = remise("MONTANT", "100000000000000000000000000000000000000000");
            })),
            ensemble([globale("REMISE_SUPERIEURE_AU_TOTAL")])
        );
    }

    #[test]
    fn remise_superieure_au_total_independante_du_regime_et_de_l_acompte() {
        assert_eq!(
            erreurs(&demande(|d| {
                d.remise_globale = remise("MONTANT", "1000");
                d.regime_tva = Saisie::Absente;
                d.acompte_pct = texte("101");
            })),
            ensemble([
                globale("REGIME_INVALIDE"),
                globale("ACOMPTE_INVALIDE"),
                globale("REMISE_SUPERIEURE_AU_TOTAL"),
            ])
        );
    }

    #[test]
    fn remise_superieure_au_total_non_verifiee_si_erreur_de_ligne() {
        assert_eq!(
            erreurs(&demande(|d| {
                d.remise_globale = remise("MONTANT", "1000");
                d.lignes = Some(vec![ligne(|_| {}), ligne(|l| l.quantite = texte("-1"))]);
            })),
            ensemble([de_ligne("QUANTITE_INVALIDE", 1)])
        );
        assert_eq!(
            erreurs(&demande(|d| {
                d.remise_globale = remise("MONTANT", "1000");
                d.regime_tva = texte("FRANCHISE_EN_BASE");
            })),
            ensemble([de_ligne("FRANCHISE_AVEC_TVA", 0)])
        );
        assert_eq!(
            erreurs(&demande(|d| {
                d.remise_globale = remise("MONTANT", "1000");
                d.lignes = Some(vec![]);
            })),
            ensemble([globale("LIGNES_VIDES")])
        );
    }

    #[test]
    fn remise_en_pourcentage_jamais_superieure_au_total() {
        assert!(
            DemandeDevis::valider(&demande(|d| d.remise_globale = remise("POURCENTAGE", "100")))
                .is_ok()
        );
    }

    #[test]
    fn toutes_les_erreurs_sont_rapportees_ensemble() {
        assert_eq!(
            erreurs(&DemandeDevisBrute {
                regime_tva: texte("AUTRE"),
                lignes: Some(vec![
                    ligne(|l| l.quantite = texte("0")),
                    LigneBrute::NonObjet,
                    ligne(|l| {
                        l.prix_unitaire_ht = texte("abc");
                        l.remise_pct = texte("150");
                    }),
                ]),
                remise_globale: remise("POURCENTAGE", "200"),
                acompte_pct: texte("30.555"),
            }),
            ensemble([
                globale("REGIME_INVALIDE"),
                globale("REMISE_GLOBALE_INVALIDE"),
                globale("ACOMPTE_INVALIDE"),
                de_ligne("QUANTITE_INVALIDE", 0),
                de_ligne("DESIGNATION_VIDE", 1),
                de_ligne("QUANTITE_INVALIDE", 1),
                de_ligne("UNITE_INVALIDE", 1),
                de_ligne("PRIX_INVALIDE", 1),
                de_ligne("TAUX_INVALIDE", 1),
                de_ligne("PRIX_INVALIDE", 2),
                de_ligne("REMISE_LIGNE_INVALIDE", 2),
            ])
        );
    }

    #[test]
    fn montant_ht_de_ligne_arrondi_au_centime() {
        let montant = |modifier: fn(&mut ChampsLigneBruts)| {
            let valide =
                DemandeDevis::valider(&demande(|d| d.lignes = Some(vec![ligne(modifier)])))
                    .expect("demande valide");
            valide.lignes()[0].montant_ht()
        };
        // Exemple de la spec : 12,5 × 45,90 × 0,95 = 545,0625 → 545,06.
        assert_eq!(montant(|_| {}), Montant::depuis_centimes(54_506));
        // 0,001 × 5,00 = 0,005 → 0,01 (demi-supérieur).
        assert_eq!(
            montant(|l| {
                l.quantite = texte("0.001");
                l.prix_unitaire_ht = texte("5");
                l.remise_pct = Saisie::Absente;
            }),
            Montant::depuis_centimes(1)
        );
        // 0,001 × 4,99 = 0,00499 → 0,00.
        assert_eq!(
            montant(|l| {
                l.quantite = texte("0.001");
                l.prix_unitaire_ht = texte("4.99");
                l.remise_pct = Saisie::Absente;
            }),
            Montant::zero()
        );
        // Remise de ligne 100 % → 0.
        assert_eq!(montant(|l| l.remise_pct = texte("100")), Montant::zero());
        // Bornes maximales : 1 000 000 × 10 000 000 = 1e13 €.
        assert_eq!(
            montant(|l| {
                l.quantite = texte("1000000");
                l.prix_unitaire_ht = texte("10000000");
                l.remise_pct = Saisie::Absente;
            }),
            Montant::depuis_centimes(1_000_000_000_000_000_i64)
        );
        // 3 × 0,10 × (1 − 33,33 %) = 0,20001 → 0,20.
        assert_eq!(
            montant(|l| {
                l.quantite = texte("3");
                l.prix_unitaire_ht = texte("0.10");
                l.remise_pct = texte("33.33");
            }),
            Montant::depuis_centimes(20)
        );
    }
}
