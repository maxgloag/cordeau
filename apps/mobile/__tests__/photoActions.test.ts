/**
 * Tests des mutations DB de suppression/retry photo.
 *
 * L'infra de test mocke expo-sqlite (pas de vrai SQLite), donc on injecte un faux
 * `db` qui enregistre les écritures réelles (table ciblée + payload). On n'assert
 * pas le comportement du mock mais notre contrat d'écriture : quelles tables sont
 * touchées et avec quelles valeurs.
 */
const mockUpdateCalls: { table: unknown; set: Record<string, unknown> }[] = [];
const mockDeleteTables: unknown[] = [];

jest.mock("../db/index", () => ({
  db: {
    update: (table: unknown) => ({
      set: (payload: Record<string, unknown>) => {
        mockUpdateCalls.push({ table, set: payload });
        return { where: () => ({ run: () => undefined }) };
      },
    }),
    delete: (table: unknown) => {
      mockDeleteTables.push(table);
      return { where: () => ({ run: () => undefined }) };
    },
  },
}));
jest.mock("expo-crypto", () => ({ randomUUID: () => "test-uuid" }));
jest.mock("expo-file-system/legacy", () => ({}));
jest.mock("@/lib/api", () => ({}), { virtual: true });

import { deleteLocalPhoto } from "../db/queries";
import { retryFailedEntry } from "../db/photoOutbox";
import { outboxPhotos, photos } from "../db/schema";

beforeEach(() => {
  mockUpdateCalls.length = 0;
  mockDeleteTables.length = 0;
});

describe("deleteLocalPhoto", () => {
  it("supprime la ligne photo ET l'entrée outbox associée (pas d'orphelin)", () => {
    deleteLocalPhoto("photo-1");
    expect(mockDeleteTables).toContain(photos);
    expect(mockDeleteTables).toContain(outboxPhotos);
  });
});

describe("retryFailedEntry", () => {
  it("repasse l'entrée en pending et remet retryCount à 0", () => {
    retryFailedEntry("entry-1");
    expect(mockUpdateCalls).toHaveLength(1);
    expect(mockUpdateCalls[0]?.table).toBe(outboxPhotos);
    expect(mockUpdateCalls[0]?.set).toMatchObject({
      status: "pending",
      retryCount: 0,
    });
  });
});
