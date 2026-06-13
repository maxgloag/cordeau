/**
 * Tests des mutations DB photo contre une VRAIE base SQLite en mémoire (sql.js).
 * On insère des lignes réelles, on appelle le code de prod, on assert sur l'état final.
 *
 * Ce fichier tourne dans le projet jest "node" (cf package.json) : environnement node
 * sans le preset jest-expo, car l'instanciation WASM de sql.js échoue sous jest-expo.
 */
import { holder, loadSqlJs, freshTestDb } from "../test-utils/testDb";

jest.mock("../db/index", () => ({
  get db() {
    return require("../test-utils/testDb").holder.db;
  },
}));
jest.mock("expo-crypto", () => ({ randomUUID: () => "generated-uuid" }));
jest.mock("expo-file-system/legacy", () => ({}));
jest.mock("@/lib/api", () => ({}), { virtual: true });

import {
  reconcilePhotos,
  deleteLocalPhoto,
  setPhotoLegendeLocal,
  getPhotosForChantier,
} from "../db/queries";
import { retryFailedEntry } from "../db/photoOutbox";
import { photos, outboxPhotos } from "../db/schema";
import type { PhotoApiResponse } from "../lib/api";

function db() {
  if (!holder.db) throw new Error("db non initialisée");
  return holder.db;
}

function insertPhoto(over: Partial<typeof photos.$inferInsert> = {}): void {
  db()
    .insert(photos)
    .values({
      id: "photo-id",
      chantierId: "chantier-1",
      status: "local",
      createdAt: 1000,
      ...over,
    })
    .run();
}

function remotePhoto(over: Partial<PhotoApiResponse> = {}): PhotoApiResponse {
  return {
    id: "remote-id",
    chantierId: "chantier-1",
    lotId: null,
    tacheId: null,
    remoteKey: "photos/x/y",
    photoUrl: "https://photos.example.com/x",
    thumbnailUrl: null,
    creeLe: "2026-06-13T10:00:00+00:00",
    legende: null,
    ...over,
  };
}

beforeAll(loadSqlJs);
beforeEach(freshTestDb);

describe("deleteLocalPhoto", () => {
  it("supprime la ligne photo ET son entrée d'outbox (pas d'orphelin)", () => {
    insertPhoto({ id: "p1" });
    db()
      .insert(outboxPhotos)
      .values({
        id: "ob1",
        photoId: "p1",
        localUri: "file://p1.jpg",
        chantierId: "chantier-1",
        status: "failed",
        retryCount: 3,
        createdAt: 1000,
      })
      .run();

    deleteLocalPhoto("p1");

    expect(getPhotosForChantier("chantier-1")).toHaveLength(0);
    expect(db().select().from(outboxPhotos).all()).toHaveLength(0);
  });

  it("ne touche pas les autres photos", () => {
    insertPhoto({ id: "p1" });
    insertPhoto({ id: "p2" });

    deleteLocalPhoto("p1");

    const restantes = getPhotosForChantier("chantier-1");
    expect(restantes.map((p) => p.id)).toEqual(["p2"]);
  });
});

describe("retryFailedEntry", () => {
  it("repasse une entrée failed en pending et remet retryCount à 0", () => {
    db()
      .insert(outboxPhotos)
      .values({
        id: "ob1",
        photoId: "p1",
        localUri: "file://p1.jpg",
        chantierId: "chantier-1",
        status: "failed",
        retryCount: 7,
        lastAttemptAt: 5000,
        createdAt: 1000,
      })
      .run();

    retryFailedEntry("ob1");

    const [entry] = db().select().from(outboxPhotos).all();
    expect(entry?.status).toBe("pending");
    expect(entry?.retryCount).toBe(0);
    expect(entry?.lastAttemptAt).toBeNull();
  });
});

describe("setPhotoLegendeLocal", () => {
  it("écrit la légende sur la photo ciblée", () => {
    insertPhoto({ id: "p1", status: "confirmed" });

    setPhotoLegendeLocal("p1", "Fissure mur nord");

    const [photo] = getPhotosForChantier("chantier-1");
    expect(photo?.legende).toBe("Fissure mur nord");
  });
});

describe("reconcilePhotos", () => {
  it("upsert les distantes, supprime les confirmed orphelines, préserve les local", () => {
    insertPhoto({
      id: "garde-confirmed",
      status: "confirmed",
      legende: "vieux",
    });
    insertPhoto({ id: "supprime-confirmed", status: "confirmed" });
    insertPhoto({ id: "garde-local", status: "local" });

    reconcilePhotos("chantier-1", [
      remotePhoto({ id: "garde-confirmed", legende: "nouveau" }),
      remotePhoto({ id: "nouvelle-distante" }),
    ]);

    const rows = getPhotosForChantier("chantier-1");
    const byId = Object.fromEntries(rows.map((r) => [r.id, r]));

    expect(Object.keys(byId).sort()).toEqual([
      "garde-confirmed",
      "garde-local",
      "nouvelle-distante",
    ]);
    expect(byId["garde-confirmed"]?.legende).toBe("nouveau");
    expect(byId["garde-local"]?.status).toBe("local");
    expect(byId["nouvelle-distante"]?.status).toBe("confirmed");
  });

  it("ne supprime jamais une photo local absente du serveur", () => {
    insertPhoto({ id: "upload-en-cours", status: "local" });

    reconcilePhotos("chantier-1", []);

    expect(getPhotosForChantier("chantier-1").map((p) => p.id)).toEqual([
      "upload-en-cours",
    ]);
  });
});
