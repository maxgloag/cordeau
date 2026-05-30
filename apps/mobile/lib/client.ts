import { z } from "zod";

export const clientSchema = z.object({
  nom: z.string().min(1, "Nom requis"),
  email: z
    .string()
    .refine((v) => v === "" || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), {
      message: "Email invalide",
    }),
  telephone: z.string(),
  adresseRue: z.string().min(1, "Adresse requise"),
  adresseCodePostal: z
    .string()
    .min(1, "Code postal requis")
    .regex(/^\d{5}$/, "5 chiffres"),
  adresseVille: z.string().min(1, "Ville requise"),
  adressePays: z.string().min(1),
  notes: z.string(),
});

export type ClientFormValues = z.infer<typeof clientSchema>;
