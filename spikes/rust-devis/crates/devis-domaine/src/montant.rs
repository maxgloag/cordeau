//! Montant monétaire exact, en centimes d'euro.

use std::fmt;

use num_bigint::BigInt;
use num_integer::Integer;
use num_traits::Signed;

use crate::decimal::DecimalNonSigne;

/// Montant en centimes d'euro, signé et sans borne (aucun dépassement possible).
///
/// L'affichage produit toujours exactement 2 décimales : `"1234.50"`, `"0.00"`.
#[derive(Debug, Clone, Default, PartialEq, Eq, PartialOrd, Ord, Hash)]
pub struct Montant(BigInt);

impl Montant {
    /// Montant nul.
    #[must_use]
    pub fn zero() -> Self {
        Self::default()
    }

    /// Construit un montant à partir d'un nombre de centimes.
    #[must_use]
    pub fn depuis_centimes(centimes: impl Into<BigInt>) -> Self {
        Self(centimes.into())
    }

    /// Lit une chaîne décimale positive ou nulle d'au plus 2 décimales, sans
    /// borne supérieure ; `None` si hors règles.
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        let centimes = DecimalNonSigne::lire(texte)?.en_unites(2)?;
        Some(Self(BigInt::from(centimes)))
    }

    /// Nombre de centimes.
    #[must_use]
    pub fn centimes(&self) -> &BigInt {
        &self.0
    }

    /// Somme de deux montants.
    #[must_use]
    pub fn plus(&self, autre: &Self) -> Self {
        Self(&self.0 + &autre.0)
    }

    /// Différence de deux montants.
    #[must_use]
    pub fn moins(&self, autre: &Self) -> Self {
        Self(&self.0 - &autre.0)
    }
}

impl<'a> std::iter::Sum<&'a Montant> for Montant {
    fn sum<I: Iterator<Item = &'a Montant>>(montants: I) -> Self {
        montants.fold(Self::zero(), |total, montant| total.plus(montant))
    }
}

impl std::iter::Sum for Montant {
    fn sum<I: Iterator<Item = Montant>>(montants: I) -> Self {
        montants.fold(Self::zero(), |total, montant| total.plus(&montant))
    }
}

impl fmt::Display for Montant {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        let signe = if self.0.is_negative() { "-" } else { "" };
        // `div_rem` par la constante 100 : ne peut pas paniquer.
        let (euros, centimes) = self.0.abs().div_rem(&BigInt::from(100_u8));
        write!(f, "{signe}{euros}.{centimes:0>2}")
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn m(centimes: i64) -> Montant {
        Montant::depuis_centimes(centimes)
    }

    #[test]
    fn s_affiche_avec_exactement_deux_decimales() {
        assert_eq!(m(123_450).to_string(), "1234.50");
        assert_eq!(m(0).to_string(), "0.00");
        assert_eq!(m(5).to_string(), "0.05");
        assert_eq!(m(10).to_string(), "0.10");
        assert_eq!(m(100).to_string(), "1.00");
        assert_eq!(m(54_506).to_string(), "545.06");
        assert_eq!(m(-1).to_string(), "-0.01");
        assert_eq!(m(-1_230).to_string(), "-12.30");
        let grand: BigInt = "123456789012345678901234567890".parse().unwrap();
        assert_eq!(
            Montant::depuis_centimes(grand).to_string(),
            "1234567890123456789012345678.90"
        );
    }

    #[test]
    fn lit_un_montant_positif_d_au_plus_deux_decimales() {
        assert_eq!(Montant::lire("16.35"), Some(m(1_635)));
        assert_eq!(Montant::lire("0"), Some(m(0)));
        assert_eq!(Montant::lire("1.5"), Some(m(150)));
        assert_eq!(Montant::lire("007.10"), Some(m(710)));
        assert_eq!(Montant::lire("1.500"), None);
        assert_eq!(Montant::lire("-1"), None);
        assert_eq!(Montant::lire("1e3"), None);
        let grand: BigInt = "99999999999999999999999999999999999900".parse().unwrap();
        assert_eq!(
            Montant::lire("999999999999999999999999999999999999"),
            Some(Montant::depuis_centimes(grand))
        );
    }

    #[test]
    fn additionne_et_soustrait() {
        assert_eq!(m(54_506).moins(&m(1_635)), m(52_871));
        assert_eq!(m(52_871).plus(&m(5_287)), m(58_158));
        assert_eq!(m(1).moins(&m(3)), m(-2));
        assert_eq!([m(1), m(2), m(3)].iter().sum::<Montant>(), m(6));
        assert_eq!(
            std::iter::empty::<&Montant>().sum::<Montant>(),
            Montant::zero()
        );
    }
}
