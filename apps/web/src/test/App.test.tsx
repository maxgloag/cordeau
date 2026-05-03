import { describe, it, expect } from "vitest";
import { render } from "vitest-browser-react";
import { AppView } from "../AppView";

describe("AppView", () => {
  it("affiche toujours le titre", async () => {
    const screen = await render(
      <AppView isLoading={false} isError={false} data={undefined} />,
    );
    await expect.element(
      screen.getByRole("heading", { name: /cordeau api/i }),
    ).toBeVisible();
  });

  it("affiche l'indicateur de chargement", async () => {
    const screen = await render(
      <AppView isLoading={true} isError={false} data={undefined} />,
    );
    await expect
      .element(screen.getByText(/vérification du statut/i))
      .toBeVisible();
  });

  it("affiche l'erreur si l'API est injoignable", async () => {
    const screen = await render(
      <AppView isLoading={false} isError={true} data={undefined} />,
    );
    await expect.element(screen.getByText(/api injoignable/i)).toBeVisible();
  });

  it("affiche le statut et la version quand les données sont disponibles", async () => {
    const screen = await render(
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
    await expect.element(screen.getByText(/0\.0\.0/)).toBeVisible();
    // "ok" apparaît deux fois (badge statut + valeur service database)
    await expect.element(screen.getByText("ok").first()).toBeVisible();
  });
});
