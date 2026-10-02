//! Tests du contrat CLI : binaire `totaux-devis` réellement exécuté, JSON sur
//! stdin, JSON sur stdout, code de sortie 0 / 1.

// Crate de test : les exemptions `allow-*-in-tests` de clippy.toml ne couvrent
// que les fonctions `#[test]`, pas les helpers de ce fichier.
#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::panic,
    clippy::indexing_slicing
)]

use std::io::Write;
use std::process::{Command, Stdio};

use serde_json::{Value, json};

/// Exécute le binaire avec `entree` sur stdin ; renvoie (JSON de sortie, code).
fn executer(entree: &[u8]) -> (Value, i32) {
    let mut processus = Command::new(env!("CARGO_BIN_EXE_totaux-devis"))
        .stdin(Stdio::piped())
        .stdout(Stdio::piped())
        .stderr(Stdio::piped())
        .spawn()
        .expect("lancement du binaire");
    processus
        .stdin
        .take()
        .expect("stdin")
        .write_all(entree)
        .expect("écriture stdin");
    let sortie = processus.wait_with_output().expect("attente du binaire");
    let json = serde_json::from_slice(&sortie.stdout).unwrap_or_else(|erreur| {
        panic!(
            "stdout non JSON ({erreur}) : {:?}",
            String::from_utf8_lossy(&sortie.stdout)
        )
    });
    (json, sortie.status.code().expect("code de sortie"))
}

fn executer_json(entree: &Value) -> (Value, i32) {
    executer(entree.to_string().as_bytes())
}

/// Erreurs triées `(code, ligne)` pour une comparaison ensembliste.
fn erreurs(sortie: &Value) -> Vec<(String, Option<u64>)> {
    let mut erreurs: Vec<_> = sortie["erreurs"]
        .as_array()
        .unwrap_or_else(|| panic!("pas de clé `erreurs` : {sortie}"))
        .iter()
        .map(|e| {
            assert_eq!(e.as_object().map(serde_json::Map::len), Some(2), "{e}");
            (
                e["code"].as_str().expect("code").to_owned(),
                e["ligne"].as_u64(),
            )
        })
        .collect();
    erreurs.sort();
    erreurs
}

fn attendu(liste: &[(&str, Option<u64>)]) -> Vec<(String, Option<u64>)> {
    let mut liste: Vec<_> = liste.iter().map(|(c, l)| ((*c).to_owned(), *l)).collect();
    liste.sort();
    liste
}

fn exemple() -> Value {
    json!({
        "regime_tva": "NORMAL",
        "lignes": [{
            "designation": "Pose carrelage sol",
            "quantite": "12.5",
            "unite": "M2",
            "prix_unitaire_ht": "45.90",
            "taux_tva": "10",
            "remise_pct": "5"
        }],
        "remise_globale": { "type": "POURCENTAGE", "valeur": "3" },
        "acompte_pct": "30"
    })
}

fn exemple_modifie(modifier: impl FnOnce(&mut Value)) -> Value {
    let mut entree = exemple();
    modifier(&mut entree);
    entree
}

fn json_invalide() -> Vec<(String, Option<u64>)> {
    attendu(&[("JSON_INVALIDE", None)])
}

#[test]
fn exemple_verifie_de_la_spec() {
    let (sortie, code) = executer_json(&exemple());
    assert_eq!(code, 0);
    assert_eq!(
        sortie,
        json!({
            "ok": {
                "lignes": [{ "montant_ht": "545.06" }],
                "total_ht_brut": "545.06",
                "remise_globale": "16.35",
                "total_ht_net": "528.71",
                "ventilation_tva": [
                    { "taux": "10", "base_ht": "528.71", "montant_tva": "52.87" }
                ],
                "total_tva": "52.87",
                "total_ttc": "581.58",
                "acompte": "174.47",
                "solde": "407.11",
                "mention_tva": null
            }
        })
    );
}

#[test]
fn franchise_en_base_sans_ventilation_avec_mention() {
    let (sortie, code) = executer_json(&json!({
        "regime_tva": "FRANCHISE_EN_BASE",
        "lignes": [
            { "designation": "Main d'œuvre", "quantite": "7", "unite": "H", "prix_unitaire_ht": "40", "taux_tva": "0" },
            { "designation": "Fournitures", "quantite": "1", "unite": "FORFAIT", "prix_unitaire_ht": "120.5", "taux_tva": "0", "remise_pct": null }
        ],
        "remise_globale": { "type": "MONTANT", "valeur": "10.50" },
        "acompte_pct": null
    }));
    assert_eq!(code, 0);
    assert_eq!(
        sortie,
        json!({
            "ok": {
                "lignes": [{ "montant_ht": "280.00" }, { "montant_ht": "120.50" }],
                "total_ht_brut": "400.50",
                "remise_globale": "10.50",
                "total_ht_net": "390.00",
                "ventilation_tva": [],
                "total_tva": "0.00",
                "total_ttc": "390.00",
                "acompte": "0.00",
                "solde": "390.00",
                "mention_tva": "TVA non applicable, art. 293 B du CGI"
            }
        })
    );
}

#[test]
fn entree_non_json_ou_racine_non_objet() {
    for entree in [
        &b""[..],
        b"{",
        b"[]",
        b"42",
        b"\"texte\"",
        b"null",
        b"true",
        b"{} {}",
        b"{\"regime_tva\": NaN}",
        b"\xff\xfe{}",
        b"\xef\xbb\xbf{}",
        b"{'regime_tva': 'NORMAL'}",
    ] {
        let (sortie, code) = executer(entree);
        assert_eq!(
            erreurs(&sortie),
            json_invalide(),
            "{:?}",
            String::from_utf8_lossy(entree)
        );
        assert_eq!(code, 1);
    }
}

#[test]
fn objet_vide() {
    let (sortie, code) = executer(b"  {}\n");
    assert_eq!(code, 1);
    assert_eq!(
        erreurs(&sortie),
        attendu(&[("REGIME_INVALIDE", None), ("LIGNES_VIDES", None)])
    );
}

#[test]
fn lignes_non_tableau_ou_vide() {
    for lignes in [json!(null), json!({}), json!("x"), json!(1), json!([])] {
        let (sortie, code) = executer_json(&exemple_modifie(|e| e["lignes"] = lignes.clone()));
        assert_eq!(code, 1);
        assert_eq!(
            erreurs(&sortie),
            attendu(&[("LIGNES_VIDES", None)]),
            "{lignes}"
        );
    }
}

#[test]
fn ligne_qui_n_est_pas_un_objet() {
    for ligne in [json!(42), json!("x"), json!([]), json!(null), json!(true)] {
        let (sortie, _) = executer_json(&exemple_modifie(|e| {
            e["lignes"].as_array_mut().unwrap().push(ligne.clone());
        }));
        assert_eq!(
            erreurs(&sortie),
            attendu(&[
                ("DESIGNATION_VIDE", Some(1)),
                ("QUANTITE_INVALIDE", Some(1)),
                ("UNITE_INVALIDE", Some(1)),
                ("PRIX_INVALIDE", Some(1)),
                ("TAUX_INVALIDE", Some(1)),
            ]),
            "{ligne}"
        );
    }
}

#[test]
fn nombre_json_a_la_place_d_une_chaine() {
    let cas: [(&str, Value, &str); 6] = [
        ("quantite", json!(12.5), "QUANTITE_INVALIDE"),
        ("prix_unitaire_ht", json!(45.9), "PRIX_INVALIDE"),
        ("taux_tva", json!(10), "TAUX_INVALIDE"),
        ("remise_pct", json!(5), "REMISE_LIGNE_INVALIDE"),
        ("designation", json!(1), "DESIGNATION_VIDE"),
        ("unite", json!(["M2"]), "UNITE_INVALIDE"),
    ];
    for (champ, valeur, code_erreur) in cas {
        let (sortie, code) =
            executer_json(&exemple_modifie(|e| e["lignes"][0][champ] = valeur.clone()));
        assert_eq!(code, 1);
        assert_eq!(
            erreurs(&sortie),
            attendu(&[(code_erreur, Some(0))]),
            "{champ}"
        );
    }
    let (sortie, _) = executer_json(&exemple_modifie(|e| e["acompte_pct"] = json!(30)));
    assert_eq!(erreurs(&sortie), attendu(&[("ACOMPTE_INVALIDE", None)]));
    let (sortie, _) = executer_json(&exemple_modifie(|e| e["regime_tva"] = json!(1)));
    assert_eq!(erreurs(&sortie), attendu(&[("REGIME_INVALIDE", None)]));
}

#[test]
fn nombre_json_hors_plage_f64_n_invalide_pas_tout_le_document() {
    let entree = exemple()
        .to_string()
        .replace("\"quantite\":\"12.5\"", "\"quantite\":1e400");
    assert!(entree.contains("1e400"));
    let (sortie, code) = executer(entree.as_bytes());
    assert_eq!(code, 1);
    assert_eq!(erreurs(&sortie), attendu(&[("QUANTITE_INVALIDE", Some(0))]));
}

#[test]
fn null_vaut_absent_pour_les_optionnels_et_invalide_pour_les_obligatoires() {
    let (sortie, code) = executer_json(&exemple_modifie(|e| {
        e["remise_globale"] = json!(null);
        e["acompte_pct"] = json!(null);
        e["lignes"][0]["remise_pct"] = json!(null);
    }));
    assert_eq!(code, 0, "{sortie}");
    assert_eq!(sortie["ok"]["total_ht_brut"], "573.75");
    assert_eq!(sortie["ok"]["remise_globale"], "0.00");
    assert_eq!(sortie["ok"]["acompte"], "0.00");

    let (sortie, code) = executer_json(&exemple_modifie(|e| {
        e["regime_tva"] = json!(null);
        e["lignes"][0]["quantite"] = json!(null);
    }));
    assert_eq!(code, 1);
    assert_eq!(
        erreurs(&sortie),
        attendu(&[("REGIME_INVALIDE", None), ("QUANTITE_INVALIDE", Some(0))])
    );
}

#[test]
fn remise_globale_mal_formee() {
    for remise in [
        json!("3"),
        json!(3),
        json!([]),
        json!({}),
        json!({ "type": "POURCENTAGE" }),
        json!({ "valeur": "3" }),
        json!({ "type": "POURCENTAGE", "valeur": 3 }),
        json!({ "type": "POURCENTAGE", "valeur": null }),
        json!({ "type": null, "valeur": "3" }),
        json!({ "type": "REMISE", "valeur": "3" }),
    ] {
        let (sortie, code) =
            executer_json(&exemple_modifie(|e| e["remise_globale"] = remise.clone()));
        assert_eq!(code, 1);
        assert_eq!(
            erreurs(&sortie),
            attendu(&[("REMISE_GLOBALE_INVALIDE", None)]),
            "{remise}"
        );
    }
}

#[test]
fn champs_inconnus_ignores() {
    let (sortie, code) = executer_json(&exemple_modifie(|e| {
        e["commentaire"] = json!({ "libre": true });
        e["lignes"][0]["reference"] = json!(42);
        e["remise_globale"]["note"] = json!("x");
    }));
    assert_eq!(code, 0);
    assert_eq!(sortie["ok"]["total_ttc"], "581.58");
}

#[test]
fn erreurs_combinees_et_remise_superieure_au_total() {
    let (sortie, code) = executer_json(&json!({
        "regime_tva": "TVA",
        "lignes": [{ "designation": "X", "quantite": "1", "unite": "U", "prix_unitaire_ht": "10", "taux_tva": "20" }],
        "remise_globale": { "type": "MONTANT", "valeur": "10.01" },
        "acompte_pct": "100.001"
    }));
    assert_eq!(code, 1);
    assert_eq!(
        erreurs(&sortie),
        attendu(&[
            ("REGIME_INVALIDE", None),
            ("REMISE_SUPERIEURE_AU_TOTAL", None),
            ("ACOMPTE_INVALIDE", None),
        ])
    );
}

#[test]
fn ventilation_multi_taux_triee() {
    let ligne = |prix: &str, taux: &str| json!({ "designation": "L", "quantite": "1", "unite": "U", "prix_unitaire_ht": prix, "taux_tva": taux });
    let (sortie, code) = executer_json(&json!({
        "regime_tva": "NORMAL",
        "lignes": [ligne("100", "20"), ligne("100", "5.5"), ligne("100", "0"), ligne("100", "10")],
        "remise_globale": { "type": "MONTANT", "valeur": "10" }
    }));
    assert_eq!(code, 0);
    let taux: Vec<&str> = sortie["ok"]["ventilation_tva"]
        .as_array()
        .unwrap()
        .iter()
        .map(|l| l["taux"].as_str().unwrap())
        .collect();
    assert_eq!(taux, ["0", "5.5", "10", "20"]);
    // 4 × arrondi(2,50) = 10,00 : pas d'écart.
    assert_eq!(sortie["ok"]["ventilation_tva"][3]["base_ht"], "97.50");
    assert_eq!(sortie["ok"]["total_ht_net"], "390.00");
}
