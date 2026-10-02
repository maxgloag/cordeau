//! Domaine pur du calcul des totaux d'un devis : validation exhaustive d'une
//! demande non fiable puis calcul exact au centime (aucun flottant, aucune I/O).
#![forbid(unsafe_code)]

mod arrondi;
mod decimal;
mod montant;
mod valeurs;

pub use montant::Montant;
pub use valeurs::{Designation, Pourcentage, PrixUnitaireHt, Quantite, RegimeTva, TauxTva, Unite};
