//! Binaire `totaux-devis` : lit une demande de devis JSON sur stdin, écrit
//! les totaux (ou les erreurs) en JSON sur stdout. Code de sortie : 0 succès,
//! 1 erreurs de validation, 2 erreur interne (E/S de sortie, sérialisation).

use std::io::{self, Read, Write};
use std::process::ExitCode;

use devis_cli::{Reponse, traiter};

fn main() -> ExitCode {
    let mut entree = Vec::new();
    // Une entrée illisible (E/S) est traitée comme non parsable : JSON_INVALIDE.
    if io::stdin().lock().read_to_end(&mut entree).is_err() {
        entree.clear();
    }
    match traiter(&entree)
        .map_err(io::Error::other)
        .and_then(|reponse| ecrire(&reponse))
    {
        Ok(code) => ExitCode::from(code),
        Err(erreur) => {
            // Rien d'autre à faire si stderr est lui-même fermé.
            let _ = writeln!(
                io::stderr().lock(),
                "totaux-devis : erreur interne : {erreur}"
            );
            ExitCode::from(2)
        }
    }
}

fn ecrire(reponse: &Reponse) -> io::Result<u8> {
    let mut sortie = io::stdout().lock();
    writeln!(sortie, "{}", reponse.json)?;
    sortie.flush()?;
    Ok(reponse.code_sortie)
}
