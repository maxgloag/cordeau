import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { AppView } from "../AppView";

// AppView est un composant pur (props only, pas de hooks/providers)
// Ce test fonctionne en jsdom sans infrastructure TanStack Query
describe("AppView", () => {
  it("affiche toujours le titre", () => {
    render(<AppView isLoading={false} isError={false} data={undefined} />);
    expect(
      screen.getByRole("heading", { name: /cordeau api/i }),
    ).toBeInTheDocument();
  });

  it("affiche l'indicateur de chargement", () => {
    render(<AppView isLoading={true} isError={false} data={undefined} />);
    expect(screen.getByText(/vérification du statut/i)).toBeInTheDocument();
  });

  it("affiche l'erreur si l'API est injoignable", () => {
    render(<AppView isLoading={false} isError={true} data={undefined} />);
    expect(screen.getByText(/api injoignable/i)).toBeInTheDocument();
  });

  it("affiche le statut et la version quand les données sont disponibles", () => {
    render(
      <AppView
        isLoading={false}
        isError={false}
        data={{
          status: "ok",
          timestamp: new Date().toISOString(),
          version: "0.0.0",
          services: { database: "ok" },
        }}
      />,
    );
    // "ok" apparaît deux fois (badge statut + valeur service) — on cible la version
    expect(screen.getByText(/0\.0\.0/)).toBeInTheDocument();
    expect(screen.getAllByText("ok")).toHaveLength(2);
  });
});
