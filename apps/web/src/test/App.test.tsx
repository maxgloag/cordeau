import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import App from "../App";

// Mocker la couche réseau au niveau du module — pas de fetch réel en jsdom
vi.mock("../lib/api", () => ({
  fetchHealth: vi.fn().mockResolvedValue({
    status: "ok" as const,
    timestamp: new Date().toISOString(),
    version: "0.0.0",
    services: { database: "ok" },
  }),
}));

function renderWithQueryClient() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={qc}>
      <App />
    </QueryClientProvider>,
  );
}

describe("App", () => {
  it("affiche le titre de l'application", () => {
    renderWithQueryClient();
    expect(
      screen.getByRole("heading", { name: /cordeau api/i }),
    ).toBeInTheDocument();
  });

  it("affiche l'indicateur de chargement au démarrage", () => {
    renderWithQueryClient();
    expect(screen.getByText(/vérification du statut/i)).toBeInTheDocument();
  });
});
