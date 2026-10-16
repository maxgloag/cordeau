import { z } from "zod";
import {
  champsAdresse,
  surfaceValide,
  verifierCodePostal,
} from "@/lib/regles-metier";

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

export const chantierSchema = z
  .object({
    ...champsAdresse,
    surfaceM2: z
      .string()
      .refine((v) => v === "" || surfaceValide(parseFloat(v)), {
        message: "Surface invalide",
      }),
    clientId: z.string(),
  })
  .superRefine(verifierCodePostal);

export type ChantierFormValues = z.infer<typeof chantierSchema>;
