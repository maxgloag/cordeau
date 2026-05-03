import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import App from "../App";

function wrapper({ children }: { children: React.ReactNode }) {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
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
  it("affiche le statut de l'API et la version", async () => {
    render(<App />, { wrapper });
    await waitFor(() => {
      expect(screen.getByText("v0.0.0", { exact: false })).toBeInTheDocument();
    });
    expect(screen.getByRole("heading", { name: /cordeau api/i })).toBeInTheDocument();
  });
});
