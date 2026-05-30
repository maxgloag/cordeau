import { outbox } from "../db/schema";
import { getTableConfig } from "drizzle-orm/sqlite-core";

jest.mock("expo-sqlite", () => ({
  openDatabaseSync: jest.fn(() => ({ exec: jest.fn(), prepare: jest.fn() })),
}));
jest.mock("drizzle-orm/expo-sqlite", () => ({
  drizzle: jest.fn(() => ({})),
}));
jest.mock("expo-crypto", () => ({
  randomUUID: jest.fn(() => "test-uuid-1234"),
}));

describe("Schéma outbox", () => {
  const config = getTableConfig(outbox);

  it("a les bonnes colonnes", () => {
    const names = config.columns.map((c) => c.name);
    expect(names).toContain("id");
    expect(names).toContain("entity_type");
    expect(names).toContain("entity_id");
    expect(names).toContain("operation");
    expect(names).toContain("payload");
    expect(names).toContain("status");
    expect(names).toContain("retry_count");
    expect(names).toContain("created_at");
    expect(names).toContain("last_attempt_at");
  });

  it("status a la valeur par défaut 'pending'", () => {
    const statusCol = config.columns.find((c) => c.name === "status");
    expect(statusCol?.default).toBe("pending");
  });

  it("retry_count a la valeur par défaut 0", () => {
    const retryCol = config.columns.find((c) => c.name === "retry_count");
    expect(retryCol?.default).toBe(0);
  });

  it("last_attempt_at est nullable", () => {
    const col = config.columns.find((c) => c.name === "last_attempt_at");
    expect(col?.notNull).toBeFalsy();
  });
});

describe("OutboxEntry type", () => {
  it("entityType accepte chantier et client", () => {
    const types: import("../db/schema").OutboxEntityType[] = [
      "chantier",
      "client",
    ];
    expect(types).toHaveLength(2);
  });

  it("operation accepte create, update, delete", () => {
    const ops: import("../db/schema").OutboxOperation[] = [
      "create",
      "update",
      "delete",
    ];
    expect(ops).toHaveLength(3);
  });

  it("status accepte les 5 états du cycle de vie", () => {
    const statuses: import("../db/schema").OutboxStatus[] = [
      "pending",
      "syncing",
      "synced",
      "error",
      "abandoned",
    ];
    expect(statuses).toHaveLength(5);
  });
});
