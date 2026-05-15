import { chantiers, clients, outbox } from "../db/schema";
import { getTableConfig } from "drizzle-orm/sqlite-core";

jest.mock("expo-sqlite", () => ({
  openDatabaseSync: jest.fn(() => ({ exec: jest.fn(), prepare: jest.fn() })),
}));

jest.mock("drizzle-orm/expo-sqlite", () => ({
  drizzle: jest.fn(() => ({})),
}));

describe("Schéma Drizzle — tables et colonnes", () => {
  it("table chantiers a les bonnes colonnes", () => {
    const config = getTableConfig(chantiers);
    const colonnes = config.columns.map((c) => c.name);
    expect(colonnes).toContain("id");
    expect(colonnes).toContain("adresse_rue");
    expect(colonnes).toContain("statut");
    expect(colonnes).toContain("client_id");
    expect(colonnes).toContain("client_nom");
    expect(colonnes).toContain("synced_at");
  });

  it("table clients a les bonnes colonnes", () => {
    const config = getTableConfig(clients);
    const colonnes = config.columns.map((c) => c.name);
    expect(colonnes).toContain("id");
    expect(colonnes).toContain("nom");
    expect(colonnes).toContain("email");
    expect(colonnes).toContain("telephone");
    expect(colonnes).toContain("notes");
    expect(colonnes).toContain("synced_at");
  });

  it("table outbox a les bonnes colonnes", () => {
    const config = getTableConfig(outbox);
    const colonnes = config.columns.map((c) => c.name);
    expect(colonnes).toContain("id");
    expect(colonnes).toContain("entity_type");
    expect(colonnes).toContain("entity_id");
    expect(colonnes).toContain("operation");
    expect(colonnes).toContain("payload");
    expect(colonnes).toContain("status");
    expect(colonnes).toContain("retry_count");
    expect(colonnes).toContain("created_at");
  });

  it("outbox a status 'pending' par défaut", () => {
    const config = getTableConfig(outbox);
    const statusCol = config.columns.find((c) => c.name === "status");
    expect(statusCol?.default).toBe("pending");
  });

  it("chantiers a statut 'en_preparation' par défaut", () => {
    const config = getTableConfig(chantiers);
    const statutCol = config.columns.find((c) => c.name === "statut");
    expect(statutCol?.default).toBe("en_preparation");
  });
});
