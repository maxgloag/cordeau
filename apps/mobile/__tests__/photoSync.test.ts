import { planPhotoDeletions } from "../db/photoSync";

describe("planPhotoDeletions — réconciliation locale ↔ serveur", () => {
  it("supprime une photo confirmed absente de la liste distante", () => {
    const toDelete = planPhotoDeletions([{ id: "a", status: "confirmed" }], []);
    expect(toDelete).toEqual(["a"]);
  });

  it("préserve une photo local (pas encore uploadée) même absente du serveur", () => {
    const toDelete = planPhotoDeletions(
      [{ id: "pending-1", status: "local" }],
      [],
    );
    expect(toDelete).toEqual([]);
  });

  it("préserve une photo confirmed toujours présente sur le serveur", () => {
    const toDelete = planPhotoDeletions(
      [{ id: "a", status: "confirmed" }],
      [{ id: "a" }],
    );
    expect(toDelete).toEqual([]);
  });

  it("ne supprime que les confirmed absentes, en présence d'un mix", () => {
    const toDelete = planPhotoDeletions(
      [
        { id: "garde-confirmed", status: "confirmed" },
        { id: "supprime-confirmed", status: "confirmed" },
        { id: "garde-local", status: "local" },
      ],
      [{ id: "garde-confirmed" }],
    );
    expect(toDelete).toEqual(["supprime-confirmed"]);
  });
});
