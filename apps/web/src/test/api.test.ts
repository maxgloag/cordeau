/**
 * @vitest-environment node
 * Tests unitaires des fonctions API — pas de DOM, pas de composant React.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";

const localStorageMock = (() => {
  const store: Record<string, string> = {};
  return {
    getItem: (key: string) => store[key] ?? null,
    setItem: (key: string, value: string) => {
      store[key] = value;
    },
    removeItem: (key: string) => {
      delete store[key];
    },
    clear: () => {
      Object.keys(store).forEach((k) => delete store[k]);
    },
  };
})();

beforeEach(() => {
  vi.stubGlobal("localStorage", localStorageMock);
  localStorageMock.clear();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("login", () => {
  it("retourne l'utilisateur connecté et stocke le token", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({
          id: "abc",
          email: "artisan@test.fr",
          token: "selector.verifier",
          refreshToken: "refreshtoken",
          expiresAt: new Date(Date.now() + 3600 * 1000).toISOString(),
        }),
      }),
    );

    const { login, getToken } = await import("../lib/api");
    const result = await login("artisan@test.fr", "Password1");

    expect(result.email).toBe("artisan@test.fr");
    expect(getToken()).toBe("selector.verifier");
  });

  it("lève une ApiError en cas de 401", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        statusText: "Unauthorized",
        json: async () => ({}),
      }),
    );

    const { login } = await import("../lib/api");
    await expect(login("x@x.fr", "wrong")).rejects.toMatchObject({
      status: 401,
    });
  });
});

describe("patchLegende", () => {
  it("envoie un PATCH merge-patch avec la légende et retourne la photo", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ id: "p1", legende: "Mur nord" }),
    });
    vi.stubGlobal("fetch", fetchMock);

    const { patchLegende } = await import("../lib/api");
    const result = await patchLegende("p1", "Mur nord");

    expect(result.legende).toBe("Mur nord");
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toContain("/api/photos/p1");
    expect(init.method).toBe("PATCH");
    expect(init.headers).toMatchObject({
      "Content-Type": "application/merge-patch+json",
    });
    expect(init.body).toBe(JSON.stringify({ legende: "Mur nord" }));
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
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => [chantier],
      }),
    );

    const { fetchChantiers } = await import("../lib/api");
    const result = await fetchChantiers();

    expect(result).toHaveLength(1);
    expect(result[0]?.adresseRue).toBe("1 rue Test");
  });
});
