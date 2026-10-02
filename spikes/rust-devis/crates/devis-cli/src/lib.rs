//! Adaptateur CLI : JSON non fiable → [`DemandeDevisBrute`] → domaine → JSON.
//!
//! Ce module ne contient **aucune règle métier** : il traduit la forme JSON
//! (absent / `null` / non-chaîne / non-objet) vers les saisies brutes du
//! domaine, puis sérialise le résultat.

use devis_domaine::{
    ChampsLigneBruts, DemandeDevis, DemandeDevisBrute, ErreurValidation, LigneBrute,
    RemiseGlobaleBrute, Saisie, TotauxDevis,
};
use serde::Serialize;
use serde_json::{Map, Value};

/// Réponse prête à écrire : document JSON et code de sortie du processus.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Reponse {
    /// Document JSON (une ligne).
    pub json: String,
    /// 0 en cas de succès, 1 en cas d'erreurs de validation.
    pub code_sortie: u8,
}

/// Traite une entrée brute (octets lus sur stdin).
///
/// # Errors
///
/// Échec de sérialisation de la sortie (erreur interne, jamais attendue).
pub fn traiter(entree: &[u8]) -> Result<Reponse, serde_json::Error> {
    let Ok(Value::Object(racine)) = serde_json::from_slice::<Value>(entree) else {
        return erreurs_vers_json(&[ErreurJson {
            code: "JSON_INVALIDE",
            ligne: None,
        }]);
    };
    match DemandeDevis::valider(&demande_brute(&racine)) {
        Ok(demande) => succes_vers_json(&demande.calculer_totaux()),
        Err(erreurs) => erreurs_vers_json(
            &erreurs
                .into_iter()
                .map(ErreurJson::from)
                .collect::<Vec<_>>(),
        ),
    }
}

// --- JSON → saisie brute -------------------------------------------------------

fn demande_brute(racine: &Map<String, Value>) -> DemandeDevisBrute {
    DemandeDevisBrute {
        regime_tva: saisie(racine.get("regime_tva")),
        lignes: match racine.get("lignes") {
            Some(Value::Array(lignes)) => Some(lignes.iter().map(ligne_brute).collect()),
            _ => None,
        },
        remise_globale: match racine.get("remise_globale") {
            None | Some(Value::Null) => RemiseGlobaleBrute::Absente,
            Some(Value::Object(remise)) => RemiseGlobaleBrute::Objet {
                type_remise: saisie(remise.get("type")),
                valeur: saisie(remise.get("valeur")),
            },
            Some(_) => RemiseGlobaleBrute::NonObjet,
        },
        acompte_pct: saisie(racine.get("acompte_pct")),
    }
}

fn ligne_brute(ligne: &Value) -> LigneBrute {
    let Value::Object(champs) = ligne else {
        return LigneBrute::NonObjet;
    };
    LigneBrute::Objet(ChampsLigneBruts {
        designation: saisie(champs.get("designation")),
        quantite: saisie(champs.get("quantite")),
        unite: saisie(champs.get("unite")),
        prix_unitaire_ht: saisie(champs.get("prix_unitaire_ht")),
        taux_tva: saisie(champs.get("taux_tva")),
        remise_pct: saisie(champs.get("remise_pct")),
    })
}

/// `null` équivaut à « absent » ; seule une chaîne JSON est du texte.
fn saisie(valeur: Option<&Value>) -> Saisie {
    match valeur {
        None | Some(Value::Null) => Saisie::Absente,
        Some(Value::String(texte)) => Saisie::Texte(texte.clone()),
        Some(_) => Saisie::NonTexte,
    }
}

// --- Domaine → JSON ------------------------------------------------------------

#[derive(Serialize)]
struct SortieSucces {
    ok: TotauxJson,
}

#[derive(Serialize)]
struct TotauxJson {
    lignes: Vec<LigneJson>,
    total_ht_brut: String,
    remise_globale: String,
    total_ht_net: String,
    ventilation_tva: Vec<VentilationJson>,
    total_tva: String,
    total_ttc: String,
    acompte: String,
    solde: String,
    mention_tva: Option<&'static str>,
}

#[derive(Serialize)]
struct LigneJson {
    montant_ht: String,
}

#[derive(Serialize)]
struct VentilationJson {
    taux: &'static str,
    base_ht: String,
    montant_tva: String,
}

#[derive(Serialize)]
struct SortieErreurs<'a> {
    erreurs: &'a [ErreurJson],
}

#[derive(Serialize)]
struct ErreurJson {
    code: &'static str,
    ligne: Option<usize>,
}

impl From<ErreurValidation> for ErreurJson {
    fn from(erreur: ErreurValidation) -> Self {
        Self {
            code: erreur.code(),
            ligne: erreur.ligne(),
        }
    }
}

fn succes_vers_json(totaux: &TotauxDevis) -> Result<Reponse, serde_json::Error> {
    let sortie = SortieSucces {
        ok: TotauxJson {
            lignes: totaux
                .lignes
                .iter()
                .map(|montant| LigneJson {
                    montant_ht: montant.to_string(),
                })
                .collect(),
            total_ht_brut: totaux.total_ht_brut.to_string(),
            remise_globale: totaux.remise_globale.to_string(),
            total_ht_net: totaux.total_ht_net.to_string(),
            ventilation_tva: totaux
                .ventilation_tva
                .iter()
                .map(|ligne| VentilationJson {
                    taux: ligne.taux.libelle(),
                    base_ht: ligne.base_ht.to_string(),
                    montant_tva: ligne.montant_tva.to_string(),
                })
                .collect(),
            total_tva: totaux.total_tva.to_string(),
            total_ttc: totaux.total_ttc.to_string(),
            acompte: totaux.acompte.to_string(),
            solde: totaux.solde.to_string(),
            mention_tva: totaux.mention_tva,
        },
    };
    Ok(Reponse {
        json: serde_json::to_string(&sortie)?,
        code_sortie: 0,
    })
}

fn erreurs_vers_json(erreurs: &[ErreurJson]) -> Result<Reponse, serde_json::Error> {
    Ok(Reponse {
        json: serde_json::to_string(&SortieErreurs { erreurs })?,
        code_sortie: 1,
    })
}
