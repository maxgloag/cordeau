//! Domaine pur du calcul des totaux d'un devis : validation exhaustive d'une
//! demande non fiable puis calcul exact au centime (aucun flottant, aucune I/O).
#![forbid(unsafe_code)]

mod arrondi;
mod calcul;
mod decimal;
mod demande;
mod montant;
mod valeurs;

pub use calcul::{LigneVentilation, MENTION_FRANCHISE, TotauxDevis};
pub use demande::{
    ChampsLigneBruts, DemandeDevis, DemandeDevisBrute, ErreurDemande, ErreurLigne, ErreurValidation,
    LigneBrute, LigneDevis, RemiseGlobale, RemiseGlobaleBrute, Saisie,
};
pub use montant::Montant;
pub use valeurs::{Designation, Pourcentage, PrixUnitaireHt, Quantite, RegimeTva, TauxTva, Unite};
