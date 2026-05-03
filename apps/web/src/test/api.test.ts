/**
 * @vitest-environment node
 * Test unitaire de fetchHealth — pas besoin de DOM, pas de composant React.
 */
import { describe, it, expect, vi, afterEach } from "vitest";

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("fetchHealth", () => {
  it("retourne la réponse de santé de l'API", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({
          status: "ok",
          timestamp: new Date().toISOString(),
          version: "0.0.0",
          services: { database: "ok" },
        }),
      }),
    );

    // Import dynamique pour que import.meta.env soit résolu au bon moment
    const { fetchHealth } = await import("../lib/api");
    const result = await fetchHealth();

    expect(result.status).toBe("ok");
    expect(result.version).toBe("0.0.0");
    expect(result.services?.["database"]).toBe("ok");
  });

  it("lève une erreur si l'API répond avec un code non-2xx", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({ ok: false, status: 503 }),
    );

    const { fetchHealth } = await import("../lib/api");
    await expect(fetchHealth()).rejects.toThrow("Health check failed: 503");
  });
});
