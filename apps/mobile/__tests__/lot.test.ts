import {
  lireEstimation,
  lotSchema,
  prochainOrdre,
  uniteDuMode,
} from "../lib/lot";

describe("lib/lot", () => {
  it("dérive l'unité du mode", () => {
    expect(uniteDuMode("temps")).toBe("h");
    expect(uniteDuMode("surface")).toBe("m²");
    expect(uniteDuMode("forfait")).toBe("€");
  });

  it("calcule l'ordre suivant, 0 pour une liste vide", () => {
    expect(prochainOrdre([])).toBe(0);
    expect(prochainOrdre([{ ordre: 0 }, { ordre: 4 }])).toBe(5);
  });

  it("lit une estimation avec virgule, vide = aucune", () => {
    expect(lireEstimation("12,5")).toBe(12.5);
    expect(lireEstimation("  ")).toBeNull();
    expect(lireEstimation("abc")).toBeNaN();
  });

  it.each([
    [{ nom: "Peinture", mode: "temps", estimation: "" }, true],
    [{ nom: "Peinture", mode: "forfait", estimation: "1500,50" }, true],
    [{ nom: "  ", mode: "temps", estimation: "" }, false],
    [{ nom: "Peinture", mode: "temps", estimation: "-1" }, false],
    [{ nom: "Peinture", mode: "temps", estimation: "abc" }, false],
    [{ nom: "Peinture", mode: "inconnu", estimation: "" }, false],
  ])("valide le formulaire de lot %j", (valeurs, attendu) => {
    expect(lotSchema.safeParse(valeurs).success).toBe(attendu);
  });
});
