import { describe, it, expect, vi, beforeEach } from "vitest";
import { act, render, screen, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import App from "../App";

function makeWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
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
});

describe("App — health check", () => {
  it("affiche le titre et le statut de l'API après réponse", async () => {
    await act(async () => {
      render(<App />, { wrapper: makeWrapper() });
    });

    expect(screen.getByRole("heading", { name: /cordeau api/i })).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText(/0\.0\.0/)).toBeInTheDocument();
    });
  });
});
