//! Lecture du format « chaîne décimale » de la spec : `^[0-9]+(\.[0-9]+)?$`.

use num_bigint::BigUint;

/// Nombre décimal non signé lu sans perte depuis une chaîne décimale :
/// valeur = `mantisse / 10^decimales`.
///
/// Les zéros finaux comptent dans `decimales` (« 1.500 » a 3 décimales).
#[derive(Debug, Clone, PartialEq, Eq)]
pub(crate) struct DecimalNonSigne {
    mantisse: BigUint,
    decimales: usize,
}

impl DecimalNonSigne {
    /// Lit une chaîne décimale ; `None` si le format n'est pas respecté.
    pub(crate) fn lire(texte: &str) -> Option<Self> {
        // Partie fractionnaire : absente, ou au moins un chiffre après le point.
        let (entiere, fraction) = match texte.split_once('.') {
            Some((entiere, fraction)) if que_des_chiffres(fraction) => (entiere, fraction),
            Some(_) => return None,
            None => (texte, ""),
        };
        if !que_des_chiffres(entiere) {
            return None;
        }
        // Après ce contrôle, `chiffres` ne contient que des chiffres ASCII :
        // `parse_bytes` (qui tolère `_` et `+`) ne voit donc que le format strict.
        let chiffres = [entiere, fraction].concat();
        let mantisse = BigUint::parse_bytes(chiffres.as_bytes(), 10)?;
        Some(Self {
            mantisse,
            decimales: fraction.len(),
        })
    }

    /// Valeur exprimée en unités de `10^-echelle` (échelle 2 = centimes),
    /// ou `None` si le nombre a plus de `echelle` décimales.
    pub(crate) fn en_unites(&self, echelle: usize) -> Option<BigUint> {
        let decalage = u32::try_from(echelle.checked_sub(self.decimales)?).ok()?;
        Some(&self.mantisse * BigUint::from(10_u32).pow(decalage))
    }
}

/// Vrai si `texte` est non vide et ne contient que des chiffres ASCII `[0-9]`.
fn que_des_chiffres(texte: &str) -> bool {
    !texte.is_empty() && texte.bytes().all(|octet| octet.is_ascii_digit())
}

#[cfg(test)]
mod tests {
    use super::*;

    fn unites(texte: &str, echelle: usize) -> Option<BigUint> {
        DecimalNonSigne::lire(texte).and_then(|d| d.en_unites(echelle))
    }

    #[test]
    fn lit_les_formats_valides_de_la_spec() {
        for texte in ["0", "12.5", "0.125", "007", "1.500", "100", "0.0"] {
            assert!(DecimalNonSigne::lire(texte).is_some(), "{texte:?} doit être valide");
        }
    }

    #[test]
    fn rejette_les_formats_invalides() {
        for texte in [
            "", ".5", "5.", "-1", "+1", "1e3", "1E3", "1,5", " 1", "1 ", "1.2.3", "1_000", ".",
            "1.5\n", "\u{0661}", "\u{ff11}", "0x10", "NaN", "inf", "1..5",
        ] {
            assert!(DecimalNonSigne::lire(texte).is_none(), "{texte:?} doit être invalide");
        }
    }

    #[test]
    fn convertit_en_unites_de_l_echelle_demandee() {
        assert_eq!(unites("12.5", 3), Some(BigUint::from(12_500_u32)));
        assert_eq!(unites("007", 2), Some(BigUint::from(700_u32)));
        assert_eq!(unites("0.125", 3), Some(BigUint::from(125_u32)));
        assert_eq!(unites("45.90", 2), Some(BigUint::from(4_590_u32)));
        assert_eq!(unites("0", 0), Some(BigUint::from(0_u32)));
    }

    #[test]
    fn les_zeros_finaux_comptent_dans_les_decimales() {
        assert_eq!(unites("1.500", 2), None);
        assert_eq!(unites("1.500", 3), Some(BigUint::from(1_500_u32)));
        assert_eq!(unites("0.125", 2), None);
    }

    #[test]
    fn accepte_les_entiers_arbitrairement_grands() {
        let texte = "123456789012345678901234567890123456789012345678901234567890.25";
        let attendu: BigUint = "12345678901234567890123456789012345678901234567890123456789025"
            .parse()
            .unwrap();
        assert_eq!(unites(texte, 2), Some(attendu));
    }
}
