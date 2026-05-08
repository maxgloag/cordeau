/**
 * @vitest-environment node
 * Tests unitaires des fonctions API — pas de DOM, pas de composant React.
 */
import { describe, it, expect, vi, afterEach } from "vitest";

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("login", () => {
  it("retourne l'utilisateur connecté", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ id: "abc", email: "artisan@test.fr" }),
      }),
    );

    const { login } = await import("../lib/api");
    const result = await login("artisan@test.fr", "Password1");

    expect(result.email).toBe("artisan@test.fr");
  });

  it("lève une ApiError en cas de 401", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({ ok: false, status: 401, statusText: "Unauthorized", json: async () => ({}) }),
    );

    const { login } = await import("../lib/api");
    await expect(login("x@x.fr", "wrong")).rejects.toMatchObject({ status: 401 });
  });
});

describe("fetchChantiers", () => {
  it("retourne la liste des chantiers", async () => {
    const chantier = {
      id: "uuid-1",
      adresseRue: "1 rue Test",
      adresseCodePostal: "75001",
      adresseVille: "Paris",
      adressePays: "FR",
      statut: "en_preparation",
    };
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => [chantier] }),
    );

    const { fetchChantiers } = await import("../lib/api");
    const result = await fetchChantiers();

    expect(result).toHaveLength(1);
    expect(result[0]?.adresseRue).toBe("1 rue Test");
  });
});
