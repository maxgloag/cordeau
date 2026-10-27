import { describe, expect, it } from "vitest";
import {
  codePostalValide,
  emailClientValide,
  emailInscriptionValide,
  estimationLotValide,
  legendeValide,
  libelleTacheValide,
  ligneAdresseValide,
  motDePasseValide,
  nomClientValide,
  nomLotValide,
  paysValide,
  surfaceValide,
} from "./regles-metier";

// Runner des fixtures de conformité partagées (packages/shared/conformite/README.md).
interface Fixture {
  regle: string;
  cas: { nom: string; entree: Record<string, unknown>; valide: boolean }[];
}

const fixtures = Object.values(
  import.meta.glob<Fixture>("../../../../packages/shared/conformite/*.json", {
    eager: true,
    import: "default",
  }),
);

const chaine = (entree: Record<string, unknown>, cle: string): string =>
  String(entree[cle]);

const implementations: Record<
  string,
  (entree: Record<string, unknown>) => boolean
> = {
  "adresse.rue": (e) => ligneAdresseValide(chaine(e, "rue")),
  "adresse.ville": (e) => ligneAdresseValide(chaine(e, "ville")),
  "adresse.codePostal": (e) =>
    codePostalValide(chaine(e, "codePostal"), chaine(e, "pays")),
  "adresse.pays": (e) => paysValide(chaine(e, "pays")),
  "chantier.surface": (e) => surfaceValide(Number(e["surfaceM2"])),
  "client.nom": (e) => nomClientValide(chaine(e, "nom")),
  "client.email": (e) => emailClientValide(chaine(e, "email")),
  "auth.email": (e) => emailInscriptionValide(chaine(e, "email")),
  "auth.motDePasse": (e) => motDePasseValide(chaine(e, "motDePasse")),
  "photo.legende": (e) => legendeValide(chaine(e, "legende")),
  "lot.nom": (e) => nomLotValide(chaine(e, "nom")),
  "lot.estimation": (e) => estimationLotValide(Number(e["estimation"])),
  "tache.libelle": (e) => libelleTacheValide(chaine(e, "libelle")),
};

describe("fixtures de conformité", () => {
  it("couvrent au moins une règle", () => {
    expect(fixtures.length).toBeGreaterThan(0);
  });

  describe.each(fixtures)("$regle", ({ regle, cas }) => {
    const implementation = implementations[regle];

    it("a une implémentation web", () => {
      expect(implementation).toBeDefined();
    });

    it.each(cas)("$nom", ({ entree, valide }) => {
      expect(implementation?.(entree)).toBe(valide);
    });
  });
});
