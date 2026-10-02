//! Value objects d'une ligne de devis. Chaque constructeur `lire` est la seule
//! porte d'entrée depuis une saisie non fiable : un objet construit respecte
//! toujours ses invariants (parse, don't validate).

use std::num::NonZeroU32;

use crate::decimal::DecimalNonSigne;

/// Quantité strictement positive, au plus 1 000 000, au plus 3 décimales.
/// Stockée en millièmes d'unité.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub struct Quantite(NonZeroU32);

impl Quantite {
    /// Lit une chaîne décimale ; `None` si hors règles.
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        lire_borne(texte, 3, 1_000_000_000).and_then(NonZeroU32::new).map(Self)
    }

    /// Quantité en millièmes d'unité.
    #[must_use]
    pub fn milliemes(self) -> u32 {
        self.0.get()
    }
}

/// Prix unitaire HT positif ou nul, au plus 10 000 000, au plus 2 décimales.
/// Stocké en centimes.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub struct PrixUnitaireHt(u32);

impl PrixUnitaireHt {
    /// Lit une chaîne décimale ; `None` si hors règles.
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        lire_borne(texte, 2, 1_000_000_000).map(Self)
    }

    /// Prix en centimes.
    #[must_use]
    pub fn centimes(self) -> u32 {
        self.0
    }
}

/// Pourcentage entre 0 et 100 inclus, au plus 2 décimales.
/// Stocké en centièmes de pour cent (100 % = 10 000).
#[derive(Debug, Clone, Copy, Default, PartialEq, Eq)]
pub struct Pourcentage(u16);

impl Pourcentage {
    /// 0 %.
    pub const ZERO: Self = Self(0);

    /// Dénominateur de [`Pourcentage::centiemes`] : 100 % = 10 000.
    pub const CENT_POUR_CENT: u16 = 10_000;

    /// Lit une chaîne décimale ; `None` si hors règles.
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        let centiemes = lire_borne(texte, 2, u32::from(Self::CENT_POUR_CENT))?;
        u16::try_from(centiemes).ok().map(Self)
    }

    /// Valeur en centièmes de pour cent.
    #[must_use]
    pub fn centiemes(self) -> u16 {
        self.0
    }
}

/// Taux de TVA autorisé (chaîne d'entrée exacte parmi 4 valeurs).
/// L'ordre de déclaration est l'ordre croissant des taux (`Ord` dérivé).
#[derive(Debug, Clone, Copy, PartialEq, Eq, PartialOrd, Ord, Hash)]
pub enum TauxTva {
    /// 0 % (ex. autoliquidation de la sous-traitance BTP, franchise en base).
    Zero,
    /// 5,5 %.
    CinqVirguleCinq,
    /// 10 %.
    Dix,
    /// 20 %.
    Vingt,
}

impl TauxTva {
    /// Lit la chaîne exacte (`"0"`, `"5.5"`, `"10"`, `"20"`).
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        match texte {
            "0" => Some(Self::Zero),
            "5.5" => Some(Self::CinqVirguleCinq),
            "10" => Some(Self::Dix),
            "20" => Some(Self::Vingt),
            _ => None,
        }
    }

    /// Chaîne canonique, identique à la saisie acceptée.
    #[must_use]
    pub fn libelle(self) -> &'static str {
        match self {
            Self::Zero => "0",
            Self::CinqVirguleCinq => "5.5",
            Self::Dix => "10",
            Self::Vingt => "20",
        }
    }

    /// Taux en pour mille (5,5 % = 55 ‰).
    #[must_use]
    pub fn pour_mille(self) -> u16 {
        match self {
            Self::Zero => 0,
            Self::CinqVirguleCinq => 55,
            Self::Dix => 100,
            Self::Vingt => 200,
        }
    }
}

/// Unité de mesure d'une ligne.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Unite {
    /// Unité.
    U,
    /// Mètre carré.
    M2,
    /// Mètre linéaire.
    Ml,
    /// Mètre cube.
    M3,
    /// Heure.
    H,
    /// Forfait.
    Forfait,
}

impl Unite {
    /// Lit la chaîne exacte (`"U"`, `"M2"`, `"ML"`, `"M3"`, `"H"`, `"FORFAIT"`).
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        match texte {
            "U" => Some(Self::U),
            "M2" => Some(Self::M2),
            "ML" => Some(Self::Ml),
            "M3" => Some(Self::M3),
            "H" => Some(Self::H),
            "FORFAIT" => Some(Self::Forfait),
            _ => None,
        }
    }
}

/// Régime de TVA du devis.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum RegimeTva {
    /// TVA collectée par taux.
    Normal,
    /// Franchise en base (art. 293 B du CGI) : pas de TVA.
    FranchiseEnBase,
}

impl RegimeTva {
    /// Lit la chaîne exacte (`"NORMAL"`, `"FRANCHISE_EN_BASE"`).
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        match texte {
            "NORMAL" => Some(Self::Normal),
            "FRANCHISE_EN_BASE" => Some(Self::FranchiseEnBase),
            _ => None,
        }
    }
}

/// Désignation non vide après suppression des blancs de début/fin.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Designation(String);

impl Designation {
    /// `None` si la chaîne est vide après `trim` (blancs Unicode).
    #[must_use]
    pub fn lire(texte: &str) -> Option<Self> {
        (!texte.trim().is_empty()).then(|| Self(texte.to_owned()))
    }

    /// Texte saisi (non tronqué).
    #[must_use]
    pub fn texte(&self) -> &str {
        &self.0
    }
}

/// Lit une chaîne décimale exprimée en unités de `10^-echelle`, bornée à
/// `maximum` (inclus) ; `None` si mal formée, trop de décimales ou trop grande.
fn lire_borne(texte: &str, echelle: usize, maximum: u32) -> Option<u32> {
    let unites = DecimalNonSigne::lire(texte)?.en_unites(echelle)?;
    u32::try_from(&unites).ok().filter(|valeur| *valeur <= maximum)
}

#[cfg(test)]
mod tests {
    use super::*;

    fn quantite(texte: &str) -> Option<u32> {
        Quantite::lire(texte).map(Quantite::milliemes)
    }

    fn prix(texte: &str) -> Option<u32> {
        PrixUnitaireHt::lire(texte).map(PrixUnitaireHt::centimes)
    }

    fn pct(texte: &str) -> Option<u16> {
        Pourcentage::lire(texte).map(Pourcentage::centiemes)
    }

    #[test]
    fn quantite_en_milliemes() {
        assert_eq!(quantite("12.5"), Some(12_500));
        assert_eq!(quantite("0.001"), Some(1));
        assert_eq!(quantite("007"), Some(7_000));
        assert_eq!(quantite("1.500"), Some(1_500));
    }

    #[test]
    fn quantite_strictement_positive() {
        assert_eq!(quantite("0"), None);
        assert_eq!(quantite("0.000"), None);
        assert_eq!(quantite("000"), None);
    }

    #[test]
    fn quantite_bornee_a_un_million_et_trois_decimales() {
        assert_eq!(quantite("1000000"), Some(1_000_000_000));
        assert_eq!(quantite("1000000.000"), Some(1_000_000_000));
        assert_eq!(quantite("1000000.001"), None);
        assert_eq!(quantite("1000001"), None);
        assert_eq!(quantite("4294968"), None, "au-delà de u32 en millièmes");
        assert_eq!(quantite("99999999999999999999999"), None);
        assert_eq!(quantite("1.2345"), None);
        assert_eq!(quantite("0.0001"), None);
        assert_eq!(quantite("1.0000"), None);
    }

    #[test]
    fn quantite_mal_formee() {
        assert_eq!(quantite("-1"), None);
        assert_eq!(quantite("1,5"), None);
        assert_eq!(quantite(""), None);
    }

    #[test]
    fn prix_en_centimes() {
        assert_eq!(prix("0"), Some(0));
        assert_eq!(prix("0.00"), Some(0));
        assert_eq!(prix("45.90"), Some(4_590));
        assert_eq!(prix("45.9"), Some(4_590));
        assert_eq!(prix("10000000"), Some(1_000_000_000));
        assert_eq!(prix("10000000.00"), Some(1_000_000_000));
    }

    #[test]
    fn prix_borne_et_deux_decimales() {
        assert_eq!(prix("10000000.01"), None);
        assert_eq!(prix("10000001"), None);
        assert_eq!(prix("1.234"), None);
        assert_eq!(prix("1.500"), None);
        assert_eq!(prix("-0"), None);
        assert_eq!(prix("1e3"), None);
    }

    #[test]
    fn pourcentage_en_centiemes() {
        assert_eq!(pct("0"), Some(0));
        assert_eq!(pct("5"), Some(500));
        assert_eq!(pct("3.25"), Some(325));
        assert_eq!(pct("100"), Some(10_000));
        assert_eq!(pct("100.00"), Some(10_000));
        assert_eq!(pct("0100"), Some(10_000));
        assert_eq!(Pourcentage::ZERO.centiemes(), 0);
        assert_eq!(Pourcentage::default(), Pourcentage::ZERO);
    }

    #[test]
    fn pourcentage_entre_0_et_100_et_deux_decimales() {
        assert_eq!(pct("100.01"), None);
        assert_eq!(pct("101"), None);
        assert_eq!(pct("1.234"), None);
        assert_eq!(pct("1.000"), None);
        assert_eq!(pct("65536"), None, "au-delà de u16");
        assert_eq!(pct("-5"), None);
    }

    #[test]
    fn taux_tva_chaines_exactes() {
        assert_eq!(TauxTva::lire("0"), Some(TauxTva::Zero));
        assert_eq!(TauxTva::lire("5.5"), Some(TauxTva::CinqVirguleCinq));
        assert_eq!(TauxTva::lire("10"), Some(TauxTva::Dix));
        assert_eq!(TauxTva::lire("20"), Some(TauxTva::Vingt));
        for texte in ["5.50", "20.0", "05.5", "020", "00", "0.0", "10 ", "", "19.6", "7"] {
            assert_eq!(TauxTva::lire(texte), None, "{texte:?}");
        }
    }

    #[test]
    fn taux_tva_libelle_et_pour_mille() {
        for (taux, libelle, pour_mille) in [
            (TauxTva::Zero, "0", 0),
            (TauxTva::CinqVirguleCinq, "5.5", 55),
            (TauxTva::Dix, "10", 100),
            (TauxTva::Vingt, "20", 200),
        ] {
            assert_eq!(taux.libelle(), libelle);
            assert_eq!(taux.pour_mille(), pour_mille);
            assert_eq!(TauxTva::lire(libelle), Some(taux));
        }
    }

    #[test]
    fn taux_tva_ordonnes_croissants() {
        assert!(TauxTva::Zero < TauxTva::CinqVirguleCinq);
        assert!(TauxTva::CinqVirguleCinq < TauxTva::Dix);
        assert!(TauxTva::Dix < TauxTva::Vingt);
    }

    #[test]
    fn unite_chaines_exactes() {
        assert_eq!(Unite::lire("U"), Some(Unite::U));
        assert_eq!(Unite::lire("M2"), Some(Unite::M2));
        assert_eq!(Unite::lire("ML"), Some(Unite::Ml));
        assert_eq!(Unite::lire("M3"), Some(Unite::M3));
        assert_eq!(Unite::lire("H"), Some(Unite::H));
        assert_eq!(Unite::lire("FORFAIT"), Some(Unite::Forfait));
        for texte in ["u", "m2", "M²", "", "FORFAIT ", "KG"] {
            assert_eq!(Unite::lire(texte), None, "{texte:?}");
        }
    }

    #[test]
    fn regime_chaines_exactes() {
        assert_eq!(RegimeTva::lire("NORMAL"), Some(RegimeTva::Normal));
        assert_eq!(
            RegimeTva::lire("FRANCHISE_EN_BASE"),
            Some(RegimeTva::FranchiseEnBase)
        );
        for texte in ["normal", "FRANCHISE", "", " NORMAL"] {
            assert_eq!(RegimeTva::lire(texte), None, "{texte:?}");
        }
    }

    #[test]
    fn designation_non_vide_apres_trim() {
        assert_eq!(
            Designation::lire("Pose carrelage").map(|d| d.texte().to_owned()),
            Some("Pose carrelage".to_owned())
        );
        assert!(Designation::lire("  x ").is_some());
        for texte in ["", " ", "   ", "\t\n", "\u{a0}", "\u{3000} \r\n"] {
            assert!(Designation::lire(texte).is_none(), "{texte:?}");
        }
    }
}
