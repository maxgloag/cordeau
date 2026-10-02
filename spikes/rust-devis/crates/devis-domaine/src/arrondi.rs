//! Arrondi exact « demi-supérieur » (`ROUND_HALF_UP`) d'une fraction entière.
//!
//! Seul module autorisé à diviser des entiers à précision arbitraire : le
//! diviseur est un [`DenominateurPositif`], ce qui rend la division par zéro
//! irreprésentable.

use num_bigint::BigInt;
use num_integer::Integer;
use num_traits::Signed;

/// Dénominateur strictement positif.
#[derive(Debug, Clone, PartialEq, Eq)]
pub(crate) struct DenominateurPositif(BigInt);

impl DenominateurPositif {
    /// `Some` si `valeur > 0`.
    pub(crate) fn nouveau(valeur: BigInt) -> Option<Self> {
        valeur.is_positive().then_some(Self(valeur))
    }

    /// `10^exposant`, toujours strictement positif.
    pub(crate) fn puissance_de_dix(exposant: u32) -> Self {
        Self(BigInt::from(10_u32).pow(exposant))
    }
}

/// `numerateur / denominateur` arrondi à l'entier le plus proche ; une moitié
/// exacte s'arrondit en s'éloignant de zéro (0,5 → 1 ; −0,5 → −1), comme
/// `ROUND_HALF_UP` de la spec (et du module `decimal` de Python).
pub(crate) fn arrondir(numerateur: &BigInt, denominateur: &DenominateurPositif) -> BigInt {
    let diviseur = &denominateur.0;
    // `div_rem` ne panique pas : le diviseur est > 0 par construction.
    let (quotient, reste) = numerateur.abs().div_rem(diviseur);
    let valeur_absolue = if &reste * 2 >= *diviseur {
        quotient + 1
    } else {
        quotient
    };
    if numerateur.is_negative() {
        -valeur_absolue
    } else {
        valeur_absolue
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use proptest::prelude::*;

    fn arr(numerateur: i64, denominateur: i64) -> i64 {
        let den = DenominateurPositif::nouveau(BigInt::from(denominateur)).unwrap();
        i64::try_from(arrondir(&BigInt::from(numerateur), &den)).unwrap()
    }

    #[test]
    fn refuse_un_denominateur_nul_ou_negatif() {
        assert!(DenominateurPositif::nouveau(BigInt::from(0)).is_none());
        assert!(DenominateurPositif::nouveau(BigInt::from(-3)).is_none());
        assert!(DenominateurPositif::nouveau(BigInt::from(1)).is_some());
    }

    #[test]
    fn puissance_de_dix() {
        let den = DenominateurPositif::puissance_de_dix(3);
        assert_eq!(arrondir(&BigInt::from(12_345), &den), BigInt::from(12));
        let un = DenominateurPositif::puissance_de_dix(0);
        assert_eq!(arrondir(&BigInt::from(7), &un), BigInt::from(7));
    }

    #[test]
    fn arrondit_la_moitie_vers_le_haut() {
        assert_eq!(arr(5, 10), 1);
        assert_eq!(arr(15, 10), 2);
        assert_eq!(arr(25, 10), 3, "pas d'arrondi bancaire");
        assert_eq!(arr(4, 10), 0);
        assert_eq!(arr(6, 10), 1);
        assert_eq!(arr(14, 1), 14);
        assert_eq!(arr(0, 7), 0);
        // 0,005 € exprimé en millièmes de centime : 5 / 10 → 1 centime.
        assert_eq!(arr(545_062_500_000, 10_000_000), 54_506);
    }

    #[test]
    fn arrondit_les_negatifs_en_s_eloignant_de_zero() {
        assert_eq!(arr(-5, 10), -1);
        assert_eq!(arr(-15, 10), -2);
        assert_eq!(arr(-4, 10), 0);
        assert_eq!(arr(-6, 10), -1);
    }

    proptest! {
        #[test]
        fn resultat_au_plus_proche_et_moitie_loin_de_zero(
            numerateur in -1_000_000_000_i64..1_000_000_000,
            denominateur in 1_i64..100_000,
        ) {
            let r = BigInt::from(arr(numerateur, denominateur));
            let (n, d) = (BigInt::from(numerateur), BigInt::from(denominateur));
            let ecart_double = (&n - &r * &d).abs() * 2;
            prop_assert!(ecart_double <= d, "pas au plus proche : {n}/{d} -> {r}");
            if ecart_double == d {
                prop_assert!((&r * &d).abs() > n.abs(), "moitié non éloignée de zéro : {n}/{d} -> {r}");
            }
        }
    }
}
