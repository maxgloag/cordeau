/**
 * Outbox Lot / Tâche contre une vraie base SQLite en mémoire (sql.js) : ordre de
 * synchronisation parent → enfant et réconciliation avec le serveur.
 */
import { holder, loadSqlJs, freshTestDb } from "../test-utils/testDb";

jest.mock("../db/index", () => ({
  get db() {
    return require("../test-utils/testDb").holder.db;
  },
}));
jest.mock("expo-crypto", () => {
  let n = 0;
  return { randomUUID: () => `outbox-${++n}` };
});
jest.mock("@/lib/api", () => ({
  creerChantier: jest.fn(),
  modifierChantier: jest.fn(),
  archiverChantier: jest.fn(),
  creerClient: jest.fn(),
  modifierClient: jest.fn(),
  supprimerClient: jest.fn(),
  patchPhotoLegende: jest.fn(),
  deletePhoto: jest.fn(),
  creerLot: jest.fn(),
  supprimerLot: jest.fn(),
  creerTache: jest.fn(),
  modifierTache: jest.fn(),
  supprimerTache: jest.fn(),
}));

import * as api from "@/lib/api";
import { processOutbox, pushToOutbox } from "../db/outbox";
import {
  insertLotLocal,
  insertTacheLocal,
  getLotsForChantier,
  getTachesForLot,
  reconcileLots,
  reconcileTaches,
} from "../db/lotQueries";
import { outbox } from "../db/schema";
import type { QueryClient } from "@tanstack/react-query";

const mocked = jest.mocked(api);
const queryClient = { setQueryData: jest.fn() } as unknown as QueryClient;

function pousser(
  entityType: "lot" | "tache",
  entityId: string,
  operation: "create" | "update" | "delete",
  payload: object,
): void {
  pushToOutbox({
    entityType,
    entityId,
    operation,
    payload: JSON.stringify(payload),
  });
}

function statuts(): Record<string, string> {
  if (!holder.db) throw new Error("db non initialisée");
  return Object.fromEntries(
    holder.db
      .select()
      .from(outbox)
      .all()
      .map((e) => [`${e.entityType}/${e.operation}/${e.entityId}`, e.status]),
  );
}

beforeAll(loadSqlJs);
beforeEach(() => {
  freshTestDb();
  jest.clearAllMocks();
  // Même milliseconde pour toutes les entrées : l'ordre repose sur le rowid.
  jest.spyOn(Date, "now").mockReturnValue(1_000);
  jest.spyOn(console, "log").mockImplementation(() => undefined);
});
afterEach(() => jest.restoreAllMocks());

describe("processOutbox : ordre Lot → Tâche", () => {
  it("envoie le lot avant ses tâches, sans le parent dans le corps", async () => {
    pousser("lot", "l1", "create", {
      chantierId: "c1",
      nom: "Peinture",
      mode: "temps",
      ordre: 0,
      uuid: "l1",
    });
    pousser("tache", "t1", "create", {
      lotId: "l1",
      libelle: "Poncer",
      ordre: 0,
      uuid: "t1",
    });
    pousser("tache", "t2", "update", { lotId: "l1", faite: true });
    mocked.creerLot.mockResolvedValue({} as never);
    mocked.creerTache.mockResolvedValue({} as never);
    mocked.modifierTache.mockResolvedValue({} as never);

    await processOutbox(queryClient);

    expect(mocked.creerLot).toHaveBeenCalledWith("c1", {
      nom: "Peinture",
      mode: "temps",
      ordre: 0,
      uuid: "l1",
    });
    expect(mocked.creerTache).toHaveBeenCalledWith("l1", {
      libelle: "Poncer",
      ordre: 0,
      uuid: "t1",
    });
    expect(mocked.modifierTache).toHaveBeenCalledWith("t2", { faite: true });
    expect(mocked.creerLot.mock.invocationCallOrder[0]).toBeLessThan(
      mocked.creerTache.mock.invocationCallOrder[0] ?? 0,
    );
  });

  it("garde les tâches en attente quand la création du lot échoue", async () => {
    pousser("lot", "l1", "create", {
      chantierId: "c1",
      nom: "Peinture",
      mode: "temps",
    });
    pousser("tache", "t1", "create", { lotId: "l1", libelle: "Poncer" });
    pousser("tache", "t1", "update", { lotId: "l1", faite: true });
    mocked.creerLot.mockRejectedValue({ status: 503 });

    await processOutbox(queryClient);

    expect(mocked.creerTache).not.toHaveBeenCalled();
    expect(mocked.modifierTache).not.toHaveBeenCalled();
    expect(statuts()).toEqual({
      "lot/create/l1": "pending",
      "tache/create/t1": "pending",
      "tache/update/t1": "pending",
    });
  });

  it("attend aussi le chantier : pas de lot tant que sa création n'est pas passée", async () => {
    pushToOutbox({
      entityType: "chantier",
      entityId: "c1",
      operation: "create",
      payload: "{}",
    });
    pousser("lot", "l1", "create", {
      chantierId: "c1",
      nom: "Peinture",
      mode: "temps",
    });
    mocked.creerChantier.mockRejectedValue({ status: 0 });

    await processOutbox(queryClient);

    expect(mocked.creerLot).not.toHaveBeenCalled();
    expect(statuts()["lot/create/l1"]).toBe("pending");
  });

  it("considère un delete 404 comme synchronisé", async () => {
    pousser("tache", "t1", "delete", { lotId: "l1" });
    mocked.supprimerTache.mockRejectedValue({ status: 404 });

    await processOutbox(queryClient);

    expect(statuts()["tache/delete/t1"]).toBe("synced");
  });
});

describe("réconciliation avec le serveur", () => {
  const lot = (id: string, over = {}) => ({
    id,
    chantierId: "c1",
    nom: id,
    mode: "temps" as const,
    estimation: null,
    reel: null,
    ordre: 0,
    ...over,
  });

  it("garde un lot créé hors ligne, supprime un lot confirmé disparu", () => {
    insertLotLocal(lot("hors-ligne"));
    insertLotLocal(lot("disparu"));
    // "disparu" a été confirmé puis supprimé ailleurs
    reconcileLots("c1", [lot("distant"), lot("disparu")]);
    reconcileLots("c1", [lot("distant")]);

    expect(
      getLotsForChantier("c1")
        .map((l) => l.id)
        .sort(),
    ).toEqual(["distant", "hors-ligne"]);
  });

  it("n'écrase pas une tâche cochée localement dont l'envoi est en attente", () => {
    insertTacheLocal({
      id: "t1",
      lotId: "l1",
      libelle: "Poncer",
      faite: true,
      faiteLe: "x",
      ordre: 0,
    });
    pousser("tache", "t1", "update", { lotId: "l1", faite: true });

    reconcileTaches("l1", [
      {
        id: "t1",
        lotId: "l1",
        libelle: "Poncer",
        faite: false,
        faiteLe: null,
        ordre: 0,
      },
    ]);

    expect(getTachesForLot("l1")[0]?.faite).toBe(true);
  });
});
