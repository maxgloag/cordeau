import { z } from "zod";

export const STATUT_LABELS: Record<string, string> = {
  en_preparation: "En préparation",
  en_cours: "En cours",
  termine: "Terminé",
  archive: "Archivé",
};

export const STATUT_COLORS: Record<string, string> = {
  en_preparation: "#3B82F6",
  en_cours: "#22C55E",
  termine: "#10B981",
  archive: "#9CA3AF",
};

export const chantierSchema = z.object({
  adresseRue: z.string().min(1, "Adresse requise"),
  adresseCodePostal: z
    .string()
    .min(1, "Code postal requis")
    .regex(/^\d{5}$/, "5 chiffres"),
  adresseVille: z.string().min(1, "Ville requise"),
  surfaceM2: z
    .string()
    .refine((v) => v === "" || (!isNaN(parseFloat(v)) && parseFloat(v) > 0), {
      message: "Surface invalide",
    }),
  clientId: z.string(),
});

export type ChantierFormValues = z.infer<typeof chantierSchema>;
