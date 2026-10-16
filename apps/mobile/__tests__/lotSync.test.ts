import { estBloquee, parentDe, planSuppressions } from "../db/lotSync";

const entree = (entityType: string, entityId: string, payload: object) => ({
  entityType,
  entityId,
  payload: JSON.stringify(payload),
});

describe("parentDe", () => {
  it("lit le chantier d'un lot et le lot d'une tâche", () => {
    expect(parentDe(entree("lot", "l1", { chantierId: "c1" }))).toBe("c1");
    expect(parentDe(entree("tache", "t1", { lotId: "l1" }))).toBe("l1");
  });

  it("ignore les autres entités et les payloads illisibles", () => {
    expect(parentDe(entree("photo", "p1", { chantierId: "c1" }))).toBeNull();
    expect(
      parentDe({ entityType: "lot", entityId: "l", payload: "{" }),
    ).toBeNull();
  });
});

describe("estBloquee", () => {
  it("bloque une tâche dont le lot n'est pas synchronisé", () => {
    expect(
      estBloquee(entree("tache", "t1", { lotId: "l1" }), new Set(["l1"])),
    ).toBe(true);
  });

  it("bloque une mise à jour dont la création n'est pas synchronisée", () => {
    expect(
      estBloquee(entree("tache", "t1", { lotId: "l1" }), new Set(["t1"])),
    ).toBe(true);
  });

  it("laisse passer quand rien ne bloque", () => {
    expect(
      estBloquee(entree("tache", "t1", { lotId: "l1" }), new Set(["autre"])),
    ).toBe(false);
  });
});

describe("planSuppressions", () => {
  const locales = [
    { id: "confirme-absent", syncedAt: 1 },
    { id: "confirme-present", syncedAt: 1 },
    { id: "cree-hors-ligne", syncedAt: null },
    { id: "en-attente", syncedAt: 1 },
  ];

  it("ne supprime que les lignes confirmées, absentes du serveur et sans sync en attente", () => {
    expect(
      planSuppressions(
        locales,
        [{ id: "confirme-present" }],
        new Set(["en-attente"]),
      ),
    ).toEqual(["confirme-absent"]);
  });
});
