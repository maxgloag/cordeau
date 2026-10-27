import { z } from "zod";
import {
  champsAdresse,
  emailClientValide,
  nomClientValide,
  verifierCodePostal,
} from "@/lib/regles-metier";

export const clientSchema = z
  .object({
    nom: z
      .string()
      .min(1, "Nom requis")
      .refine(nomClientValide, "Nom trop long"),
    email: z.string().refine(emailClientValide, "Email invalide"),
    telephone: z.string(),
    ...champsAdresse,
    notes: z.string(),
  })
  .superRefine(verifierCodePostal);

export type ClientFormValues = z.infer<typeof clientSchema>;
