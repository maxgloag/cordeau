// Règles métier dupliquées depuis l'API, qui en est la référence.
// Leur conformité est vérifiée par les fixtures de packages/shared/conformite :
// les runners testent les prédicats, les schémas Zod ci-dessous les composent.
import { z } from "zod";

const LONGUEUR_MAX_TEXTE = 255;
const CODE_POSTAL_MAX = 20;
const SURFACE_MAX_M2 = 100_000;
export const LEGENDE_MAX = 280;
const ESTIMATION_LOT_MAX = 9_999_999_999;

// Motif "html5" de la contrainte Email de Symfony.
const EMAIL_HTML5 =
  /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;

// trim() de PHP ne retire que ces caractères (pas l'espace insécable, contrairement à String.trim).
function trimPhp(valeur: string): string {
  return valeur.replace(/^[ \t\n\r\0\v]+|[ \t\n\r\0\v]+$/g, "");
}

// Symfony compte les longueurs en caractères Unicode, pas en unités UTF-16.
function longueur(valeur: string): number {
  return [...valeur].length;
}

export function ligneAdresseValide(valeur: string): boolean {
  return trimPhp(valeur) !== "" && longueur(valeur) <= LONGUEUR_MAX_TEXTE;
}

export function codePostalValide(codePostal: string, pays: string): boolean {
  if (pays === "FR") {
    return /^\d{5}$/.test(codePostal);
  }
  return trimPhp(codePostal) !== "" && longueur(codePostal) <= CODE_POSTAL_MAX;
}

export function paysValide(pays: string): boolean {
  return /^[A-Z]{2}$/.test(pays);
}

export function surfaceValide(surfaceM2: number): boolean {
  return surfaceM2 > 0 && surfaceM2 <= SURFACE_MAX_M2;
}

export function nomClientValide(nom: string): boolean {
  return nom !== "" && longueur(nom) <= LONGUEUR_MAX_TEXTE;
}

function emailValide(email: string): boolean {
  return EMAIL_HTML5.test(email) && longueur(email) <= LONGUEUR_MAX_TEXTE;
}

export function emailClientValide(email: string): boolean {
  return email === "" || emailValide(email);
}

export function emailInscriptionValide(email: string): boolean {
  return emailValide(trimPhp(email));
}

const REGLES_MOT_DE_PASSE: ReadonlyArray<{
  respectee: (motDePasse: string) => boolean;
  message: string;
}> = [
  { respectee: (v) => longueur(v) >= 8, message: "8 caractères minimum" },
  {
    respectee: (v) => longueur(v) <= LONGUEUR_MAX_TEXTE,
    message: `${LONGUEUR_MAX_TEXTE} caractères maximum`,
  },
  { respectee: (v) => /[A-Z]/.test(v), message: "Au moins une majuscule" },
  { respectee: (v) => /[0-9]/.test(v), message: "Au moins un chiffre" },
];

export function motDePasseValide(motDePasse: string): boolean {
  return REGLES_MOT_DE_PASSE.every((r) => r.respectee(motDePasse));
}

export function legendeValide(legende: string): boolean {
  return longueur(legende) <= LEGENDE_MAX;
}

export function nomLotValide(nom: string): boolean {
  return trimPhp(nom) !== "" && longueur(nom) <= LONGUEUR_MAX_TEXTE;
}

export function libelleTacheValide(libelle: string): boolean {
  return trimPhp(libelle) !== "" && longueur(libelle) <= LONGUEUR_MAX_TEXTE;
}

export function estimationLotValide(estimation: number): boolean {
  return estimation >= 0 && estimation <= ESTIMATION_LOT_MAX;
}

// Champs d'adresse des formulaires chantier et client, à compléter par verifierCodePostal.
export const champsAdresse = {
  adresseRue: z.string().refine(ligneAdresseValide, "Adresse requise"),
  adresseCodePostal: z.string().min(1, "Code postal requis"),
  adresseVille: z.string().refine(ligneAdresseValide, "Ville requise"),
  adressePays: z.string().refine(paysValide, "Pays invalide"),
};

// Le format du code postal dépend du pays : contrôle au niveau de l'objet.
export function verifierCodePostal(
  v: { adresseCodePostal: string; adressePays: string },
  ctx: z.RefinementCtx,
): void {
  if (
    v.adresseCodePostal !== "" &&
    !codePostalValide(v.adresseCodePostal, v.adressePays)
  ) {
    ctx.addIssue({
      code: "custom",
      message: "Format invalide (5 chiffres en France)",
      path: ["adresseCodePostal"],
    });
  }
}

export const motDePasseSchema = REGLES_MOT_DE_PASSE.reduce(
  (schema, { respectee, message }) => schema.refine(respectee, message),
  z.string(),
);
