import { z } from "zod";
import type { ModeFacturation } from "@/lib/api";
import {
  estimationLotValide,
  libelleTacheValide,
  nomLotValide,
} from "@/lib/regles-metier";

export const MODES: {
  valeur: ModeFacturation;
  label: string;
  unite: string;
}[] = [
  { valeur: "temps", label: "Au temps", unite: "h" },
  { valeur: "surface", label: "Au m²", unite: "m²" },
  { valeur: "forfait", label: "Forfait", unite: "€" },
];

/** L'unité se dérive du mode, jamais stockée (ADR 0024). */
export function uniteDuMode(mode: ModeFacturation): string {
  return MODES.find((m) => m.valeur === mode)?.unite ?? "";
}

export function prochainOrdre(existants: { ordre: number }[]): number {
  return existants.reduce((max, e) => Math.max(max, e.ordre), -1) + 1;
}

/** "12,5" → 12.5 ; vide → null ; illisible → NaN. */
export function lireEstimation(saisie: string): number | null {
  const propre = saisie.trim().replace(",", ".");
  return propre === "" ? null : Number(propre);
}

export const lotSchema = z.object({
  nom: z.string().refine(nomLotValide, "Nom requis (255 caractères max)"),
  mode: z.enum(["temps", "surface", "forfait"]),
  estimation: z.string().refine((v) => {
    const n = lireEstimation(v);
    return n === null || (Number.isFinite(n) && estimationLotValide(n));
  }, "Estimation invalide"),
});

export type LotFormValues = z.infer<typeof lotSchema>;

export const tacheLibelleSchema = z
  .string()
  .refine(libelleTacheValide, "Libellé requis (255 caractères max)");
