import type { ModeFacturation } from "@/lib/api";

// Libellés du mode ; l'unité n'est affichée en saisie qu'avant création du lot,
// ensuite l'API la fournit (`Lot.unite`, ADR 0024).
export const MODES: ReadonlyArray<{
  value: ModeFacturation;
  label: string;
  unite: string;
}> = [
  { value: "temps", label: "Temps", unite: "h" },
  { value: "surface", label: "Surface", unite: "m²" },
  { value: "forfait", label: "Forfait", unite: "€" },
];

export function uniteDuMode(mode: ModeFacturation): string {
  return MODES.find((m) => m.value === mode)?.unite ?? "";
}
